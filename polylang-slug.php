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
 * Version:           0.2.1
 * Author:            Ulrich Pogson
 * Author URI:        http://ulrich.pogson.ch/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       polylang-slug
 * Domain Path:       /languages
 */

// Built using code from: https://wordpress.org/support/topic/plugin-polylang-identical-page-names-in-different-languages?replies=8#post-2669927

// Check if PLL exists & the minimum version is correct.
if ( ! defined( 'POLYLANG_VERSION' ) || version_compare( POLYLANG_VERSION, '1.7', '=<' ) || version_compare( $GLOBALS[ 'wp_version' ], '4.0', '=<' ) ) {
	add_action( 'admin_notices', 'polylang_slug_admin_notices' );
	return;
}

/**
 * Minimum version admin notice.
 *
 * @since 0.2.0
 */
function polylang_slug_admin_notices() {
	echo '<div class="error"><p>' . __( 'Polylang Slug requires Polylang v1.7 and WordPress 4.0', 'polylang-slug') . '</p></div>';
}

/**
 * Checks if the slug is unique within language.
 *
 * @since 0.1.0
 *
 * @global  wpdb  $wpdb        WordPress database abstraction object.
 *
 * @param  string $slug        The desired slug (post_name).
 * @param  int    $post_ID     Post ID.
 * @param  string $post_status No uniqueness checks are made if the post is still draft or pending.
 * @param  string $post_type   Post type.
 * @param  int    $post_parent Post parent ID.
 *
 * @return string              Unique slug for the post within language, based on $post_name (with a -1, -2, etc. suffix).
 */
function polylang_slug_unique_slug_in_language( $slug, $post_ID, $post_status, $post_type, $post_parent, $original_slug ){

	// Return slug if it was not changed.
	if ( $original_slug === $slug ) {
		return $slug;
	}

	global $wpdb;

	// Get language of a post
	$lang = pll_get_post_language( $post_ID );
	$options = get_option( 'polylang' );

	// return the slug if Polylang does not return post language or has incompatable redirect setting or is not translated post type.
	if ( empty( $lang ) || 0 === $options['force_lang'] || ! pll_is_translated_post_type( $post_type ) ) {
		return $slug;
	}

	// " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID".
	$join_clause  = polylang_slug_model_post_join_clause();
	// " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")".
	$where_clause = polylang_slug_model_post_where_clause( $lang );

	// Polylang does not translate attachements - skip if it is one.
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
 * Modify the sql query to include checks for the current language.
 *
 * @since 0.1.0
 *
 * @global wpdb   $wpdb  WordPress database abstraction object.
 *
 * @param  string $query Database query.
 *
 * @return string        The modified query.
 */
function polylang_slug_filter_queries( $query ) {
	global $wpdb;

	// Query for posts page, pages, attachments and hierarchical CPT. This is the only possible place to make the change. The SQL query is set in get_page_by_path()
	$is_pages_sql = preg_match(
		"#SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} .*#",
		polylang_slug_standardize_query( $query ),
		$matches
	);

	if ( ! $is_pages_sql ) {
		return $query;
	}

	// Check if should contine. Don't add $query polylang_slug_should_run() as $query is a SQL query.
	if ( ! polylang_slug_should_run() ) {
		return $query;
	}

	$lang = pll_current_language();
	// " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID".
	$join_clause  = polylang_slug_model_post_join_clause();
	// " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")".
	$where_clause = polylang_slug_model_post_where_clause( $lang );

	$query = preg_match(
		"#(SELECT .* (?=FROM))(FROM .* (?=WHERE))(?:(WHERE .*(?=ORDER))|(WHERE .*$))(.*)#",
		polylang_slug_standardize_query( $query ),
		$matches
	);

	// Reindex array numerically $matches[3] and $matches[4] are not added together thus leaving a gap. With this $matches[5] moves up to $matches[4]
	$matches = array_values( $matches );

	// SELECT, FROM, INNER JOIN, WHERE, WHERE CLAUSE (additional), ORBER BY (if included)
	$sql_query = $matches[1] . $matches[2] . $join_clause . $matches[3] . $where_clause . $matches[4];

	/**
	 * Disable front end query modification.
	 *
	 * Allows disabling front end query modification if not needed.
	 *
	 * @since 0.2.0
	 *
	 * @param string $sql_query    Database query.
	 * @param array  $matches {
	 *     @type string $matches[1] SELECT SQL Query.
	 *     @type string $matches[2] FROM SQL Query.
	 *     @type string $matches[3] WHERE SQL Query.
	 *     @type string $matches[4] End of SQL Query (Possibly ORDER BY).
	 * }
	 * @param string $join_clause  INNER JOIN Polylang clause.
	 * @param string $where_clause Additional Polylang WHERE clause.
	 */
	$query = apply_filters( 'polylang_slug_sql_query', $sql_query, $matches, $join_clause, $where_clause );


	return $query;
}
add_filter( 'query', 'polylang_slug_filter_queries' );

/**
 * Extend the WHERE clause of the query.
 *
 * This allows the query to return only the posts of the current language
 *
 * @since 0.1.0
 *
 * @param  string   $where The WHERE clause of the query.
 * @param  WP_Query $query The WP_Query instance (passed by reference).
 *
 * @return string          The WHERE clause of the query.
 */
function polylang_slug_posts_where_filter( $where, $query ) {
	// Check if should contine.
	if ( ! polylang_slug_should_run( $query ) ) {
		return $where;
	}

	$lang = empty( $query->query['lang'] ) ? pll_current_language() : $query->query['lang'];

	// " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")"
	$where .= polylang_slug_model_post_where_clause( $lang  );

	return $where;
}
add_filter( 'posts_where', 'polylang_slug_posts_where_filter', 10, 2 );

/**
 * Extend the JOIN clause of the query.
 *
 * This allows the query to return only the posts of the current language
 *
 * @since 0.1.0
 *
 * @param  string   $join  The JOIN clause of the query.
 * @param  WP_Query $query The WP_Query instance (passed by reference).
 *
 * @return string          The JOIN clause of the query.
 */
function polylang_slug_posts_join_filter( $join, $query ) {

	// Check if should contine.
	if ( ! polylang_slug_should_run( $query ) ) {
		return $join;
	}

	// " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = ID".
	$join .= polylang_slug_model_post_join_clause();

	return $join;
}
add_filter( 'posts_join', 'polylang_slug_posts_join_filter', 10, 2 );

/**
 * Check if the query needs to be adapted.
 *
 * @since 0.2.0
 *
 * @param  WP_Query $query The WP_Query instance (passed by reference).
 *
 * @return bool
 */
function polylang_slug_should_run( $query = '' ) {

	/**
	 * Disable front end query modification.
	 *
	 * Allows disabling front end query modification if not needed.
	 *
	 * @since 0.2.0
	 *
	 * @param bool     false  Not disabling run.
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 */
	
	// Do not run in admin or if Polylang is disabled
	$disable = apply_filters( 'polylang_slug_disable', false, $query ); 
	if ( is_admin() || ! function_exists( 'pll_current_language' ) || $disable ) {
		return false;
	}
	// The lang query should be defined if the URL contains the language
	$lang          = empty( $query->query['lang'] ) ? pll_current_language() : $query->query['lang'];
	// Checks if the post type is translated when doing a custom query with the post type defined
	$is_translated = ! empty( $query->query['post_type'] ) && ! pll_is_translated_post_type( $query->query['post_type'] );

	if ( empty( $lang ) || $is_translated ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Standardize the query.
 *
 * This makes the standardized and simpler to run regex on
 *
 * @since 0.2.0
 *
 * @param  string $query Database query.
 *
 * @return string        The standardized query.
 */
function polylang_slug_standardize_query( $query ) {
	// Strip tabs, newlines and multiple spaces.
	$query = str_replace(
		array( "\t", " \n", "\n", " \r", "\r", "   ", "  " ),
		array( '', ' ', ' ', ' ', ' ', ' ', ' ' ),
		$query
	);
	return trim( $query );
}

/**
 * Fetch the polylang join clause.
 *
 * @since 0.2.0
 *
 * @return string
 */
function polylang_slug_model_post_join_clause() {
	if ( function_exists( 'PLL' ) ) {
		return PLL()->model->post->join_clause();
	} elseif ( array_key_exists( 'polylang', $GLOBALS ) ) {
		global $polylang;
		return $polylang->model->join_clause( 'post' );
	} else {
		return;
	}
}

/**
 * Fetch the polylang where clause.
 *
 * @since 0.2.0
 *
 * @param  string $lang The current language slug.
 *
 * @return string
 */
function polylang_slug_model_post_where_clause( $lang = '' ) {
	if ( function_exists( 'PLL' ) ) {
		return PLL()->model->post->where_clause( $lang );
	} elseif ( array_key_exists( 'polylang', $GLOBALS ) ) {
		global $polylang;
		return $polylang->model->where_clause( $lang, 'post' );
	} else {
		return;
	}
}
