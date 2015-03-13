<?php
// https://developer.wordpress.org/reference/hooks/query/
function polylang_slug_filter_queries( $sql ) {
	global $wpdb, $polylang;
	// keep a record of the queries
	$queries[] = $sql;

	$is_main_sql = preg_match( "#\n\t\tSELECT ID, post_name, post_parent, post_type\n\t\tFROM {$wpdb->posts}\n\t\tWHERE post_name IN \(([^)]+)\)\n\t\tAND post_type IN \(([^)]+)\)#", $sql, $matches );

	if ( $is_main_sql ) {

		$lang = pll_current_language();

		// " INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = " . ('term' == $type ? "t.term_id" : "ID");
		$join_clause  = $polylang->model->join_clause( 'post' );
		// " AND pll_tr.term_taxonomy_id IN (" . implode(',', $languages) . ")"
		$where_clause = $polylang->model->where_clause( $lang, 'post' );

		$sql = "SELECT ID, post_name, post_parent, post_type
				FROM {$wpdb->posts}
				$join_clause
				WHERE post_name IN ({$matches[1]})
				AND post_type IN ({$matches[2]})
				$where_clause";
	}

	return $sql;
}
add_filter( 'query', 'polylang_slug_filter_queries' );
