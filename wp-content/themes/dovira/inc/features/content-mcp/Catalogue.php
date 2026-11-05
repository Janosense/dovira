<?php

namespace dovira\ContentMcp;

/**
 * The connector's tool catalogue: the one list the ability registration and
 * the `dovira` MCP server both read (docs/features/content-mcp/FEATURE.md →
 * Interfaces). An ability that is not named here is not on the server.
 */
final class Catalogue {

	/** The ability category every `dovira/*` ability belongs to. */
	public const CATEGORY = 'dovira';

	/**
	 * The abilities the server lists as tools, in the order a chat uses them:
	 * reads before writes; among the writes, what a new post needs (a term,
	 * an image) before the post, its Russian copy after it, trashing last.
	 */
	public const TOOLS = [
		DescribeSite::NAME,
		ListPosts::NAME,
		GetPost::NAME,
		FindPriceRows::NAME,
		ListTerms::NAME,
		ListMedia::NAME,
		UpdatePriceRow::NAME,
		UpdatePost::NAME,
		CreateTerm::NAME,
		UpdateTerm::NAME,
		UploadMedia::NAME,
		CreatePost::NAME,
		CreateTranslation::NAME,
		TrashPost::NAME,
	];
}
