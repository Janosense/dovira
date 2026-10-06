<?php
/**
 * Plugin Name:       Dovira Bridge — звернення з сайту
 * Plugin URI:        https://dovira.vet/
 * Description:       Дає Dovira Bridge (Dovira Pro) читати звернення сайту (тип запису «conversation») і змінювати їх статус «оброблено / не оброблено» через REST API (простір dovira-bridge/v1). Доступ — лише користувачу з правом редагувати чужі звернення, вхід паролем застосунку.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Dovira
 * License:           GPL-2.0-or-later
 * Text Domain:       dovira-bridge-conversations
 *
 * Single file, no dependencies. Routes (all require an authenticated user with
 * the post type's "edit others" capability, e.g. edit_others_conversations):
 *
 *   GET  /wp-json/dovira-bridge/v1/describe
 *   GET  /wp-json/dovira-bridge/v1/conversations?modified_after=<ISO8601 UTC>&page=1&per_page=50
 *   GET  /wp-json/dovira-bridge/v1/conversations/<id>
 *   POST /wp-json/dovira-bridge/v1/conversations/<id>/status   {field_kind, key, value}
 *
 * Deactivating or deleting the plugin removes nothing: it stores no data of its own.
 */

if (!defined('ABSPATH')) {
	exit;
}

define('DOVIRA_BRIDGE_CONV_VERSION', '1.0.0');
define('DOVIRA_BRIDGE_CONV_NS', 'dovira-bridge/v1');
define('DOVIRA_BRIDGE_CONV_TYPE', 'conversation');

/**
 * The capability a caller needs: edit_others_conversations when some role
 * has it (or the post type maps to it), else edit_others_posts.
 */
function dovira_bridge_conv_capability()
{
	$pto = get_post_type_object(DOVIRA_BRIDGE_CONV_TYPE);
	if ($pto && isset($pto->cap->edit_others_posts) && $pto->cap->edit_others_posts === 'edit_others_conversations') {
		return 'edit_others_conversations';
	}
	if (function_exists('wp_roles')) {
		foreach (wp_roles()->roles as $role) {
			if (!empty($role['capabilities']['edit_others_conversations'])) {
				return 'edit_others_conversations';
			}
		}
	}
	return 'edit_others_posts';
}

/** Permission callback of every route. */
function dovira_bridge_conv_permission()
{
	if (!is_user_logged_in()) {
		return new WP_Error('rest_not_logged_in', 'Authentication required (application password).', array('status' => 401));
	}
	if (!current_user_can(dovira_bridge_conv_capability())) {
		return new WP_Error('rest_forbidden', 'This user may not edit conversations.', array('status' => 403));
	}
	if (!post_type_exists(DOVIRA_BRIDGE_CONV_TYPE)) {
		return new WP_Error('dovira_no_post_type', 'The post type "conversation" is not registered.', array('status' => 404));
	}
	return true;
}

/** ISO 8601 UTC of a GMT MySQL datetime ('' for the zero date). */
function dovira_bridge_conv_iso($gmt)
{
	if (empty($gmt) || $gmt === '0000-00-00 00:00:00') {
		return '';
	}
	$ts = strtotime($gmt . ' UTC');
	return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : '';
}

/** Registered post statuses a conversation may have (no internal ones). */
function dovira_bridge_conv_statuses()
{
	$out = array();
	foreach (get_post_stati(array(), 'objects') as $name => $st) {
		if (in_array($name, array('auto-draft', 'inherit', 'trash'), true)) {
			continue;
		}
		$out[] = $name;
	}
	return $out;
}

/** True for meta keys that are not shown: protected (leading underscore). */
function dovira_bridge_conv_private_key($key)
{
	return $key === '' || $key[0] === '_';
}

/** A meta value as JSON-friendly data. */
function dovira_bridge_conv_value($v)
{
	$v = maybe_unserialize($v);
	if (is_object($v)) {
		$v = (array) $v;
	}
	return $v;
}

/** One conversation post as a record. */
function dovira_bridge_conv_record($post)
{
	$meta = array();
	foreach (get_post_meta($post->ID) as $key => $values) {
		if (dovira_bridge_conv_private_key($key)) {
			continue;
		}
		$vals = array_map('dovira_bridge_conv_value', (array) $values);
		$meta[$key] = count($vals) === 1 ? $vals[0] : $vals;
	}
	$acf = null;
	if (function_exists('get_fields')) {
		$fields = get_fields($post->ID);
		if (is_array($fields)) {
			$acf = array();
			foreach ($fields as $k => $v) {
				$acf[$k] = is_object($v) ? (isset($v->ID) ? $v->ID : (array) $v) : $v;
			}
		}
	}
	$terms = array();
	foreach (get_object_taxonomies(DOVIRA_BRIDGE_CONV_TYPE) as $tax) {
		$list = wp_get_object_terms($post->ID, $tax);
		if (is_wp_error($list)) {
			continue;
		}
		$terms[$tax] = array();
		foreach ($list as $t) {
			$terms[$tax][] = array('slug' => $t->slug, 'name' => $t->name);
		}
	}
	$author = get_userdata((int) $post->post_author);
	return array(
		'id'           => (int) $post->ID,
		'post_status'  => $post->post_status,
		'title'        => get_the_title($post),
		'content'      => (string) $post->post_content,
		'content_text' => trim(wp_strip_all_tags((string) $post->post_content)),
		'date_gmt'     => dovira_bridge_conv_iso($post->post_date_gmt),
		'modified_gmt' => dovira_bridge_conv_iso($post->post_modified_gmt),
		'author'       => array(
			'id'   => (int) $post->post_author,
			'name' => $author ? $author->display_name : '',
		),
		'edit_link'    => admin_url('post.php?post=' . (int) $post->ID . '&action=edit'),
		'meta'         => (object) $meta,
		'acf'          => $acf === null ? null : (object) $acf,
		'terms'        => (object) $terms,
	);
}

/** The conversation post of a route id, or an error. */
function dovira_bridge_conv_post($id)
{
	$post = get_post((int) $id);
	if (!$post || $post->post_type !== DOVIRA_BRIDGE_CONV_TYPE) {
		return new WP_Error('dovira_not_found', 'Conversation not found.', array('status' => 404));
	}
	return $post;
}

/** ACF fields attached to the post type: name => {label, type, choices}. */
function dovira_bridge_conv_acf_fields()
{
	$out = array();
	if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
		return $out;
	}
	foreach (acf_get_field_groups(array('post_type' => DOVIRA_BRIDGE_CONV_TYPE)) as $group) {
		$fields = acf_get_fields($group);
		if (!is_array($fields)) {
			continue;
		}
		foreach ($fields as $f) {
			if (empty($f['name'])) {
				continue;
			}
			$out[$f['name']] = array(
				'key'     => isset($f['key']) ? $f['key'] : '',
				'label'   => isset($f['label']) ? $f['label'] : '',
				'type'    => isset($f['type']) ? $f['type'] : '',
				'choices' => isset($f['choices']) && is_array($f['choices']) ? (object) $f['choices'] : null,
				'group'   => isset($group['title']) ? $group['title'] : '',
			);
		}
	}
	return $out;
}

/** GET /describe — where the data and the «оброблено» mark may live. */
function dovira_bridge_conv_describe(WP_REST_Request $req)
{
	global $wpdb;
	$pto = get_post_type_object(DOVIRA_BRIDGE_CONV_TYPE);

	$statuses = array();
	foreach (get_post_stati(array(), 'objects') as $name => $st) {
		$statuses[] = array(
			'name'      => $name,
			'label'     => isset($st->label) ? (string) $st->label : $name,
			'public'    => !empty($st->public),
			'private'   => !empty($st->private),
			'protected' => !empty($st->protected),
			'internal'  => !empty($st->internal),
			'builtin'   => !empty($st->_builtin),
		);
	}

	$taxonomies = array();
	foreach (get_object_taxonomies(DOVIRA_BRIDGE_CONV_TYPE, 'objects') as $tax) {
		$terms = get_terms(array('taxonomy' => $tax->name, 'hide_empty' => false, 'number' => 200));
		$list = array();
		if (!is_wp_error($terms)) {
			foreach ($terms as $t) {
				$list[] = array('slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count);
			}
		}
		$taxonomies[] = array('name' => $tax->name, 'label' => (string) $tax->label, 'hierarchical' => !empty($tax->hierarchical), 'terms' => $list);
	}

	// Meta keys of the latest 50 conversations (any status).
	$ids = get_posts(array(
		'post_type'      => DOVIRA_BRIDGE_CONV_TYPE,
		'post_status'    => dovira_bridge_conv_statuses(),
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	));
	$acf = dovira_bridge_conv_acf_fields();
	$meta = array();
	if (!empty($ids)) {
		$in = implode(',', array_map('intval', $ids));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only.
		$rows = $wpdb->get_results("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($in)", ARRAY_A);
		$keys = array();
		$refs = array();
		foreach ((array) $rows as $r) {
			$k = (string) $r['meta_key'];
			$v = (string) $r['meta_value'];
			if (dovira_bridge_conv_private_key($k)) {
				// ACF stores "_<name>" => "field_<hash>" next to each value.
				if (strlen($k) > 1 && preg_match('/^field_[A-Za-z0-9]+$/', $v)) {
					$refs[substr($k, 1)] = $v;
				}
				continue;
			}
			if (!isset($keys[$k])) {
				$keys[$k] = array('posts' => 0, 'values' => array());
			}
			$keys[$k]['posts']++;
			if (count($keys[$k]['values']) <= 8) {
				$keys[$k]['values'][$v] = true;
			}
		}
		foreach ($keys as $k => $info) {
			$values = array_keys($info['values']);
			// Sample values only for short low-cardinality fields (statuses,
			// flags), never contacts or free text.
			$sample = array();
			if (count($values) <= 8) {
				foreach ($values as $v) {
					$digits = preg_replace('/\D+/', '', $v);
					if (strlen($v) > 40 || strpos($v, '@') !== false || strlen($digits) >= 7) {
						$sample = array();
						break;
					}
					$sample[] = $v;
				}
			}
			$entry = array('key' => $k, 'posts' => $info['posts'], 'values' => $sample);
			if (isset($refs[$k])) {
				$entry['acf_field'] = $refs[$k];
			}
			if (isset($acf[$k])) {
				$entry['acf'] = $acf[$k];
			} elseif (isset($refs[$k]) && function_exists('acf_get_field')) {
				$f = acf_get_field($refs[$k]);
				if (is_array($f)) {
					$entry['acf'] = array(
						'key'     => $refs[$k],
						'label'   => isset($f['label']) ? $f['label'] : '',
						'type'    => isset($f['type']) ? $f['type'] : '',
						'choices' => isset($f['choices']) && is_array($f['choices']) ? (object) $f['choices'] : null,
					);
				}
			}
			$meta[] = $entry;
		}
		usort($meta, function ($a, $b) {
			return strcmp($a['key'], $b['key']);
		});
	}

	$counts = array();
	foreach ((array) wp_count_posts(DOVIRA_BRIDGE_CONV_TYPE) as $st => $n) {
		$counts[$st] = (int) $n;
	}

	return rest_ensure_response(array(
		'plugin_version' => DOVIRA_BRIDGE_CONV_VERSION,
		'post_type'      => DOVIRA_BRIDGE_CONV_TYPE,
		'capability'     => dovira_bridge_conv_capability(),
		'labels'         => $pto ? (object) get_object_vars($pto->labels) : null,
		'supports'       => array_keys(get_all_post_type_supports(DOVIRA_BRIDGE_CONV_TYPE)),
		'statuses'       => $statuses,
		'taxonomies'     => $taxonomies,
		'meta_keys'      => $meta,
		'acf_fields'     => (object) $acf,
		'counts'         => (object) $counts,
		'sampled_posts'  => count($ids),
		'server_time_gmt' => gmdate('Y-m-d\TH:i:s\Z'),
	));
}

/** GET /conversations — oldest modification first, from modified_after. */
function dovira_bridge_conv_list(WP_REST_Request $req)
{
	$per = (int) $req->get_param('per_page');
	if ($per <= 0) {
		$per = 50;
	}
	$per = min($per, 100);
	$page = max(1, (int) $req->get_param('page'));
	$args = array(
		'post_type'           => DOVIRA_BRIDGE_CONV_TYPE,
		'post_status'         => dovira_bridge_conv_statuses(),
		'posts_per_page'      => $per,
		'paged'               => $page,
		'orderby'             => array('modified' => 'ASC', 'ID' => 'ASC'),
		'ignore_sticky_posts' => true,
		'suppress_filters'    => true,
	);
	$after = trim((string) $req->get_param('modified_after'));
	if ($after !== '') {
		$ts = strtotime($after);
		if ($ts === false) {
			return new WP_Error('dovira_bad_date', 'modified_after must be an ISO 8601 date-time.', array('status' => 400));
		}
		$args['date_query'] = array(array(
			'column'    => 'post_modified_gmt',
			'after'     => gmdate('Y-m-d H:i:s', $ts),
			'inclusive' => true,
		));
	}
	$q = new WP_Query($args);
	$items = array();
	foreach ($q->posts as $post) {
		$items[] = dovira_bridge_conv_record($post);
	}
	return rest_ensure_response(array(
		'items'           => $items,
		'page'            => $page,
		'per_page'        => $per,
		'total'           => (int) $q->found_posts,
		'pages'           => (int) $q->max_num_pages,
		'server_time_gmt' => gmdate('Y-m-d\TH:i:s\Z'),
	));
}

/** GET /conversations/<id>. */
function dovira_bridge_conv_get(WP_REST_Request $req)
{
	$post = dovira_bridge_conv_post($req['id']);
	if (is_wp_error($post)) {
		return $post;
	}
	return rest_ensure_response(dovira_bridge_conv_record($post));
}

/** POST /conversations/<id>/status — set post_status, a meta field or a term. */
function dovira_bridge_conv_set_status(WP_REST_Request $req)
{
	$post = dovira_bridge_conv_post($req['id']);
	if (is_wp_error($post)) {
		return $post;
	}
	$kind = (string) $req->get_param('field_kind');
	$key = trim((string) $req->get_param('key'));
	$raw = $req->get_param('value');
	if (!is_string($raw) && !is_numeric($raw)) {
		return new WP_Error('dovira_bad_value', 'value must be a string.', array('status' => 400));
	}
	$value = sanitize_text_field((string) $raw);
	if (strlen($value) > 191) {
		return new WP_Error('dovira_bad_value', 'value is too long.', array('status' => 400));
	}
	switch ($kind) {
		case 'post_status':
			if ($value === '' || !in_array($value, dovira_bridge_conv_statuses(), true)) {
				return new WP_Error('dovira_bad_status', 'Unknown post status.', array('status' => 400));
			}
			if ($post->post_status !== $value) {
				$res = wp_update_post(array('ID' => $post->ID, 'post_status' => $value), true);
				if (is_wp_error($res)) {
					return new WP_Error('dovira_update_failed', $res->get_error_message(), array('status' => 500));
				}
			}
			break;
		case 'meta':
			if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-:.]{0,190}$/', $key)) {
				return new WP_Error('dovira_bad_key', 'key must be a public meta key (no leading underscore).', array('status' => 400));
			}
			$acf = function_exists('get_field_object') ? get_field_object($key, $post->ID, false, false) : false;
			if (is_array($acf) && function_exists('update_field')) {
				update_field($key, $value, $post->ID);
			} elseif ($value === '') {
				delete_post_meta($post->ID, $key);
			} else {
				update_post_meta($post->ID, $key, $value);
			}
			break;
		case 'taxonomy':
			if (!in_array($key, get_object_taxonomies(DOVIRA_BRIDGE_CONV_TYPE), true)) {
				return new WP_Error('dovira_bad_taxonomy', 'The taxonomy is not attached to conversations.', array('status' => 400));
			}
			if ($value === '') {
				$res = wp_set_object_terms($post->ID, array(), $key, false);
			} else {
				$term = get_term_by('slug', $value, $key);
				if (!$term) {
					$term = get_term_by('name', $value, $key);
				}
				if (!$term) {
					return new WP_Error('dovira_bad_term', 'Unknown term of the taxonomy.', array('status' => 400));
				}
				$res = wp_set_object_terms($post->ID, array((int) $term->term_id), $key, false);
			}
			if (is_wp_error($res)) {
				return new WP_Error('dovira_update_failed', $res->get_error_message(), array('status' => 500));
			}
			break;
		default:
			return new WP_Error('dovira_bad_kind', 'field_kind must be post_status, meta or taxonomy.', array('status' => 400));
	}
	clean_post_cache($post->ID);
	return rest_ensure_response(dovira_bridge_conv_record(get_post($post->ID)));
}

/**
 * A meta field or a term changed outside the editor (a list toggle, an AJAX
 * button) does not touch post_modified; Bridge reads changes by modification
 * time, so such a change bumps it.
 */
function dovira_bridge_conv_touch($post_id)
{
	global $wpdb;
	static $done = array();
	$post_id = (int) $post_id;
	if ($post_id <= 0 || isset($done[$post_id]) || get_post_type($post_id) !== DOVIRA_BRIDGE_CONV_TYPE) {
		return;
	}
	$done[$post_id] = true;
	$wpdb->update(
		$wpdb->posts,
		array('post_modified' => current_time('mysql'), 'post_modified_gmt' => current_time('mysql', 1)),
		array('ID' => $post_id)
	);
	clean_post_cache($post_id);
}

function dovira_bridge_conv_meta_changed($meta_id, $post_id, $meta_key)
{
	if (in_array($meta_key, array('_edit_lock', '_edit_last', '_wp_old_slug'), true)) {
		return;
	}
	dovira_bridge_conv_touch($post_id);
}

add_action('updated_post_meta', 'dovira_bridge_conv_meta_changed', 10, 3);
add_action('added_post_meta', 'dovira_bridge_conv_meta_changed', 10, 3);
add_action('deleted_post_meta', function ($meta_ids, $post_id, $meta_key) {
	dovira_bridge_conv_meta_changed(0, $post_id, $meta_key);
}, 10, 3);
add_action('set_object_terms', function ($object_id) {
	dovira_bridge_conv_touch($object_id);
}, 10, 1);

add_action('rest_api_init', function () {
	$id_arg = array(
		'id' => array(
			'validate_callback' => function ($v) {
				return is_numeric($v) && (int) $v > 0;
			},
		),
	);
	register_rest_route(DOVIRA_BRIDGE_CONV_NS, '/describe', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'dovira_bridge_conv_describe',
		'permission_callback' => 'dovira_bridge_conv_permission',
	));
	register_rest_route(DOVIRA_BRIDGE_CONV_NS, '/conversations', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'dovira_bridge_conv_list',
		'permission_callback' => 'dovira_bridge_conv_permission',
		'args'                => array(
			'modified_after' => array('type' => 'string', 'required' => false),
			'page'           => array('type' => 'integer', 'required' => false, 'minimum' => 1),
			'per_page'       => array('type' => 'integer', 'required' => false, 'minimum' => 1, 'maximum' => 100),
		),
	));
	register_rest_route(DOVIRA_BRIDGE_CONV_NS, '/conversations/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'dovira_bridge_conv_get',
		'permission_callback' => 'dovira_bridge_conv_permission',
		'args'                => $id_arg,
	));
	register_rest_route(DOVIRA_BRIDGE_CONV_NS, '/conversations/(?P<id>\d+)/status', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'dovira_bridge_conv_set_status',
		'permission_callback' => 'dovira_bridge_conv_permission',
		'args'                => array_merge($id_arg, array(
			'field_kind' => array('type' => 'string', 'required' => true, 'enum' => array('post_status', 'meta', 'taxonomy')),
			'key'        => array('type' => 'string', 'required' => false, 'default' => ''),
			'value'      => array('required' => true),
		)),
	));
});
