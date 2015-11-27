<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Dashboard. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             0.1.0
 * @package           Polylang_Slug
 *
 * @wordpress-plugin
 * Plugin Name:       Polylang Slug
 * Plugin URI:        https://github.com/grappler/polylang-slug
 * GitHub Plugin URI: https://github.com/grappler/polylang-slug
 * Description:       Allows same slug for multiple languages in Polylang
 * Version:           0.1.0
 * Author:            Ulrich Pogson
 * Author URI:        http://ulrich.pogson.ch/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       polylang-slug
 * Domain Path:       /languages
 */


// Built using code from: https://wordpress.org/support/topic/plugin-polylang-identical-page-names-in-different-languages?replies=8#post-2669927

// Check if Polylang_Base exists and if $polylang is the right object
if ( ! class_exists( 'PLL_Model' ) && version_compare( $GLOBALS[ 'wp_version' ], '3.9', '<' ) ) {
	return;
}

/**
 * Checks if the slug is unique within language.
 *
 * @since 0.1.0
 *
 * @global wpdb     $wpdb     WordPress database abstraction object.
 * @global StdClass $polylang
 *
 * @param string $slug        The desired slug (post_name).
 * @param int    $post_ID     Post ID.
 * @param string $post_status No uniqueness checks are made if the post is still draft or pending.
 * @param string $post_type   Post type.
 * @param int    $post_parent Post parent ID.
 * @return string Unique slug for the post within language, based on $post_name (with a -1, -2, etc. suffix)
 */
function polylang_slug_unique_slug_in_language( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ){

	// Return slug if it was not changed
	if ( $original_slug === $slug ) {
		return $slug;
	}

	global $wpdb, $polylang;

	// Get language of a post
	$lang = $polylang->model->get_post_language( $post_ID );
	$options = get_option( 'polylang' );

	// return the slug if Polylang does not return post language or has incompatable redirect setting or is not translated post type
	if ( empty( $lang ) || 0 === $options['force_lang'] || ! $polylang->model->is_translated_post_type( $post_type ) ) {
		return $slug;
	}

	// " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = " . ('term' == $type ? "t.term_id" : "ID");
	$join_clause  = $polylang->model->join_clause( 'post' );
	// " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")"
	$where_clause = $polylang->model->where_clause( $lang, 'post' );

	// Polylang does not translate attachements - skip if it is one
	// @TODO Recheck this with the Polylang settings
	if ( 'attachment' == $post_type ) {

		// Attachment slugs must be unique across all types.
		$check_sql = "SELECT post_name FROM $wpdb->posts $join_clause WHERE post_name = %s AND ID != %d $where_clause LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_ID ) );

	} elseif ( is_post_type_hierarchical( $post_type ) ) {

		// Page slugs must be unique within their own trees. Pages are in a separate
		// namespace than posts so page slugs are allowed to overlap post slugs.
		$check_sql = "SELECT ID FROM $wpdb->posts $join_clause WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d $where_clause LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID, $post_parent ) );

	} else {

		// Post slugs must be unique across all posts.
		$check_sql = "SELECT post_name FROM $wpdb->posts $join_clause WHERE post_name = %s AND post_type = %s AND ID != %d $where_clause LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $original_slug, $post_type, $post_ID ) );

	}

	if ( ! $post_name_check ) {
		return $original_slug;
	} else {
		return $slug;
	}

}
add_filter( 'wp_unique_post_slug', 'polylang_slug_unique_slug_in_language', 10, 6 );

/**
 * Modify the sql query to include checks for the current language
 *
 * @since 0.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @global StdClass $polylang
 *
 * @param string $query Database query.
 *
 * @return string The modified query
 */
function polylang_slug_filter_queries( $query ) {
	global $wpdb, $polylang;
	// keep a record of the queries
	$queries[] = $query;

	$is_main_sql = preg_match(
		"#SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} WHERE post_name IN \(([^)]+)\) AND post_type IN \(([^)]+)\)#",
		trim(str_replace(array("\t", "\n"), array( '', ' ' ), $query)),
		$matches );

	if ( $is_main_sql ) {

		$lang = pll_current_language();

		// " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = " . ('term' == $type ? "t.term_id" : "ID");
		$join_clause  = $polylang->model->join_clause( 'post' );
		// " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")"
		$where_clause = '';
		if ( $lang ) {
			$where_clause = $polylang->model->where_clause( $lang, 'post' );
		}

		$query = "SELECT ID, post_name, post_parent, post_type
				FROM {$wpdb->posts}
				$join_clause
				WHERE post_name IN ({$matches[1]})
				AND post_type IN ({$matches[2]})
				$where_clause";
	}

	return $query;
}
add_filter( 'query', 'polylang_slug_filter_queries' );

/**
 * Extend the WHERE clause of the query
 *
 * This allows the query to return only the posts of the current language
 *
 * @since 0.1.0
 *
 * @global StdClass $polylang
 *
 * @param string   $where The WHERE clause of the query.
 * @param WP_Query $query The WP_Query instance (passed by reference).
 * @return string
 */
function polylang_slug_posts_where_filter( $where, $query ) {
	global $polylang;

	if ( is_admin() || ! empty( $query->query['lang'] ) || ! empty( $query->query['post_type'] ) && ! $polylang->model->is_translated_post_type( $query->query['post_type'] ) ) {
		return $where;
	}

	$lang = pll_current_language();

	// " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")"
	$where .= $polylang->model->where_clause( $lang, 'post' );

	return $where;
}
add_filter( 'posts_where', 'polylang_slug_posts_where_filter', 10, 2 );

/**
 * Extend the JOIN clause of the query
 *
 * This allows the query to return only the posts of the current language
 *
 * @since 0.1.0
 *
 * @global StdClass $polylang
 *
 * @param string   $join  The JOIN clause of the query.
 * @param WP_Query $query The WP_Query instance (passed by reference).
 * @return string
 */
function polylang_slug_posts_join_filter( $join, $query ) {
	global $polylang;

	if ( is_admin() || ! empty( $query->query['lang'] ) || ! empty( $query->query['post_type'] ) && ! $polylang->model->is_translated_post_type( $query->query['post_type'] ) ) {
		return $join;
	}

	// " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = " . ('term' == $type ? "t.term_id" : "ID");
	$join .= $polylang->model->join_clause( 'post' );

	return $join;
}
add_filter( 'posts_join', 'polylang_slug_posts_join_filter', 10, 2 );
