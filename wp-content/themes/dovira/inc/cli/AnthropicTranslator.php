<?php

namespace dovira\CLI;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use RuntimeException;
use WP_CLI;

/**
 * Translates a map of strings via the Anthropic Messages API.
 */
class AnthropicTranslator {

	private const MODEL = 'claude-opus-4-8';

	private const MAX_TOKENS = 16000;

	/**
	 * Chunking thresholds: a page exceeding these is translated in several calls.
	 */
	private const CHUNK_MAX_ITEMS = 100;

	private const CHUNK_MAX_CHARS = 8000;

	private const SCHEMA = [
		'type'                 => 'object',
		'properties'           => [
			'translations' => [
				'type'  => 'array',
				'items' => [
					'type'                 => 'object',
					'properties'           => [
						'key'   => [
							'type'        => 'string',
							'description' => 'Opaque path identifier, copied verbatim from the input',
						],
						'value' => [
							'type'        => 'string',
							'description' => 'Translated text with HTML and placeholders preserved',
						],
					],
					'required'             => [ 'key', 'value' ],
					'additionalProperties' => false,
				],
			],
		],
		'required'             => [ 'translations' ],
		'additionalProperties' => false,
	];

	private Client $client;

	private string $system_prompt;

	public function __construct( string $api_key, string $from_language, string $to_language ) {
		$this->client = new Client(
			apiKey: $api_key,
			requestOptions: [ 'maxRetries' => 4, 'timeout' => 300.0 ],
		);

		$this->system_prompt = <<<PROMPT
You are a professional {$from_language} to {$to_language} translator for "Dovira", a veterinary clinic and animal blood bank brand.

You receive a JSON object mapping opaque path keys to source strings in $from_language. Translate every string to natural, fluent $to_language.

Rules:
- Return each "key" byte-for-byte unchanged. Translate only the "value".
- Preserve all HTML tags, attributes, and entities exactly; translate only human-readable text between or within them.
- Preserve placeholders, shortcodes in square brackets, Yoast variables like %%title%%, URLs, emails, phone numbers, numbers, and line breaks exactly as they appear.
- Do not transliterate proper brand names unless a standard $to_language form exists.
- Keep the tone warm, professional, and trustworthy. Do not add, omit, or summarize content.
- Output strictly via the provided JSON schema, one item per input key, no commentary.
PROMPT;
	}

	/**
	 * Translate a path => string map, preserving keys.
	 *
	 * @param array<string, string> $map
	 *
	 * @return array<string, string>
	 * @throws RuntimeException When the API call or response parsing fails.
	 */
	public function translate_map( array $map ): array {
		if ( empty( $map ) ) {
			return [];
		}

		$result = [];

		foreach ( $this->chunk_map( $map ) as $chunk ) {
			$result += $this->translate_chunk( $chunk );
		}

		// Any key the model dropped falls back to the source string.
		foreach ( $map as $key => $value ) {
			if ( ! array_key_exists( $key, $result ) ) {
				WP_CLI::warning( "Translation missing for \"$key\" — keeping source text." );
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Split the map into API-call-sized chunks (keys stay globally unique).
	 *
	 * @param array<string, string> $map
	 *
	 * @return array<int, array<string, string>>
	 */
	private function chunk_map( array $map ): array {
		$chunks  = [];
		$current = [];
		$chars   = 0;

		foreach ( $map as $key => $value ) {
			$length = mb_strlen( $value );

			if ( $current && ( count( $current ) >= self::CHUNK_MAX_ITEMS || $chars + $length > self::CHUNK_MAX_CHARS ) ) {
				$chunks[] = $current;
				$current  = [];
				$chars    = 0;
			}

			$current[ $key ] = $value;
			$chars          += $length;
		}

		if ( $current ) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	/**
	 * Run one Messages API call for a chunk of strings.
	 *
	 * @param array<string, string> $chunk
	 *
	 * @return array<string, string>
	 */
	private function translate_chunk( array $chunk ): array {
		$payload = json_encode( $chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		try {
			$message = $this->client->messages->create(
				maxTokens: self::MAX_TOKENS,
				messages: [ [ 'role' => 'user', 'content' => $payload ] ],
				model: self::MODEL,
				system: [
					[
						'type'         => 'text',
						'text'         => $this->system_prompt,
						'cacheControl' => [ 'type' => 'ephemeral' ],
					],
				],
				thinking: [ 'type' => 'adaptive' ],
				outputConfig: [
					'format' => [
						'type'   => 'json_schema',
						'schema' => self::SCHEMA,
					],
				],
			);
		} catch ( APIStatusException $e ) {
			throw new RuntimeException( 'Anthropic API error (' . ( $e->type?->value ?? 'unknown' ) . '): ' . $e->getMessage(), 0, $e );
		}

		WP_CLI::debug(
			sprintf(
				'Anthropic usage — input: %d, output: %d, cache read: %d',
				$message->usage->inputTokens,
				$message->usage->outputTokens,
				$message->usage->cacheReadInputTokens ?? 0
			),
			'dovira'
		);

		$json = null;

		foreach ( $message->content as $block ) {
			if ( $block->type === 'text' ) {
				$json = $block->text;
				break;
			}
		}

		if ( $json === null ) {
			throw new RuntimeException( 'Anthropic API returned no text content (stop reason: ' . $message->stopReason . ').' );
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || ! isset( $data['translations'] ) || ! is_array( $data['translations'] ) ) {
			throw new RuntimeException( 'Could not parse translation response as JSON.' );
		}

		$result = [];

		foreach ( $data['translations'] as $row ) {
			if ( isset( $row['key'], $row['value'] ) && array_key_exists( $row['key'], $chunk ) ) {
				$result[ $row['key'] ] = $row['value'];
			}
		}

		return $result;
	}
}
