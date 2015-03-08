<?php

function filter_queries( $sql ) {
	global $wpdb, $pagenow, $polylang;
	// keep a record of the queries
	$this->queries[ ] = $sql;

	$current_language = $this->get_current_language();

	$lang = $polylang->model->get_post_language( $post_ID );
	$join_clause  = $polylang->model->join_clause('post');
	$where_clause = $polylang->model->where_clause( $lang, 'post');

	if ( $pagenow == 'categories.php' || $pagenow == 'edit-tags.php' ) {
		if ( preg_match( '#^SELECT COUNT\(\*\) FROM ' . $wpdb->term_taxonomy . ' WHERE taxonomy = \'(category|post_tag)\' $#', $sql, $matches ) ) {
			$element_type = 'tax_' . $matches[ 1 ];
			$sql          = "
				SELECT COUNT(*) FROM {$wpdb->term_taxonomy} tx
					JOIN {$wpdb->prefix}icl_translations tr ON tx.term_taxonomy_id=tr.element_id
				WHERE tx.taxonomy='{$matches[1]}' AND tr.element_type='{$element_type}' AND tr.language_code='" . $current_language . "'";
		}
	}

	if ( $pagenow == 'edit.php' || $pagenow == 'edit-pages.php' ) {
		$post_type    = isset( $_GET[ 'post_type' ] ) ? $_GET[ 'post_type' ] : 'post';
		$element_type = 'post_' . $post_type;
		if ( $this->is_translated_post_type( $post_type ) ) {
			if ( preg_match( '#SELECT post_status, COUNT\( \* \) AS num_posts FROM ' . $wpdb->posts . ' WHERE post_type = \'(.+)\' GROUP BY post_status#i', $sql, $matches ) ) {
				if ( 'all' != $current_language ) {
					$sql = '
					SELECT post_status, COUNT( * ) AS num_posts
					FROM ' . $wpdb->posts . ' p
						JOIN ' . $wpdb->prefix . 'icl_translations t ON p.ID = t.element_id
					WHERE p.post_type = \'' . $matches[ 1 ] . '\'
						AND t.element_type=\'' . $element_type . '\'
						AND t.language_code=\'' . $current_language . '\'
					GROUP BY post_status';
				} else {
					$sql = '
					SELECT post_status, COUNT( * ) AS num_posts
					FROM ' . $wpdb->posts . ' p
						JOIN ' . $wpdb->prefix . 'icl_translations t ON p.ID = t.element_id
						JOIN ' . $wpdb->prefix . 'icl_languages l ON t.language_code = l.code AND l.active = 1
					WHERE p.post_type = \'' . $matches[ 1 ] . '\'
						AND t.element_type=\'' . $element_type . '\'
					GROUP BY post_status';
				}
			}
		}
	}

	if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'ajax-tag-search' ) {
		$search = 'SELECT t.name FROM ' . $wpdb->term_taxonomy . ' AS tt INNER JOIN ' . $wpdb->terms . ' AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = \'' . esc_sql( $_GET[ 'tax' ] ) . '\' AND t.name LIKE (\'%' . esc_sql( $_GET[ 'q' ] ) . '%\')';
		if ( $sql == $search ) {
			$parts = parse_url( $_SERVER[ 'HTTP_REFERER' ] );
			@parse_str( $parts[ 'query' ], $query );
			$lang         = isset( $query[ 'lang' ] ) ? $query[ 'lang' ] : $this->get_language_cookie();
			$element_type = 'tax_' . $_GET[ 'tax' ];
			$sql          = 'SELECT t.name FROM ' . $wpdb->term_taxonomy . ' AS tt
				INNER JOIN ' . $wpdb->terms . ' AS t ON tt.term_id = t.term_id
				JOIN ' . $wpdb->prefix . 'icl_translations tr ON tt.term_taxonomy_id = tr.element_id
				WHERE tt.taxonomy = \'' . esc_sql( $_GET[ 'tax' ] ) . '\' AND tr.language_code=\'' . $lang . '\' AND element_type=\'' . $element_type . '\'
				AND t.name LIKE (\'%' . esc_sql( $_GET[ 'q' ] ) . '%\')
			';
		}
	}

	// filter get_page_by_path WP 3.9+
	if ( version_compare( $GLOBALS[ 'wp_version' ], '3.9', '>=' ) ) {
		if ( preg_match( "#\n\t\tSELECT ID, post_name, post_parent, post_type\n\t\tFROM {$wpdb->posts}\n\t\tWHERE post_name IN \(([^)]+)\)\n\t\tAND post_type IN \(([^)]+)\)#", $sql, $matches ) ) {

			//add 'post_' at the beginning of each post type
			$post_types = explode( ',', str_replace('\'', '', $matches[2]) );
			$element_types = array();
			foreach ($post_types as $post_type){
					$element_types[] = "'post_".$post_type."'";
			}
			$element_types = implode(',', $element_types);

			$sql = "SELECT p.ID, p.post_name, p.post_parent, post_type
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type IN ({$element_types}) AND t.language_code='" . $current_language . "'
					WHERE p.post_name IN ({$matches[1]}) AND p.post_type IN ({$matches[2]})
					ORDER BY t.language_code='" . $current_language . "' DESC
					";
			// added order by to ensure that we get the result in teh current language first
		}
	}elseif( version_compare( $GLOBALS[ 'wp_version' ], '3.5', '>=' ) ){
		if ( preg_match( "#SELECT ID, post_name, post_parent, post_type FROM {$wpdb->posts} WHERE post_name IN \(([^)]+)\) AND \(post_type = '([^']+)' OR post_type = 'attachment'\)#", $sql, $matches ) ) {
			$sql = "SELECT p.ID, p.post_name, p.post_parent, post_type
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type = 'post_{$matches[2]}' AND t.language_code='" . $current_language . "'
					WHERE p.post_name IN ({$matches[1]}) AND (p.post_type = '{$matches[2]}' OR p.post_type = 'attachment')
					ORDER BY t.language_code='" . $current_language . "' DESC
					";
			// added order by to ensure that we get the result in teh current language first
		}
	} else {
		// filter get_page_by_path WP 3.3+
		if ( preg_match( "#SELECT ID, post_name, post_parent FROM {$wpdb->posts} WHERE post_name IN \(([^)]+)\) AND \(post_type = '([^']+)' OR post_type = 'attachment'\)#", $sql, $matches ) ) {
			$sql = "SELECT p.ID, p.post_name, p.post_parent
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type = 'post_{$matches[2]}' AND t.language_code='" . $current_language . "'
					WHERE p.post_name IN ({$matches[1]}) AND (p.post_type = '{$matches[2]}' OR p.post_type = 'attachment')
					ORDER BY t.language_code='" . $current_language . "' DESC
					";
			// added order by to ensure that we get the result in teh current language first
		} // filter get_page_by_path < WP 3.3
		elseif ( preg_match( "#SELECT ID, post_name, post_parent FROM {$wpdb->posts} WHERE post_name = '([^']+)' AND \(post_type = '([^']+)' OR post_type = 'attachment'\)#", $sql, $matches ) ) {
			$sql = "SELECT p.ID, p.post_name, p.post_parent
					FROM {$wpdb->posts} p
					JOIN {$wpdb->prefix}icl_translations t on t.element_id = p.ID AND t.element_type = 'post_{$matches[2]}'
					WHERE p.post_name = '{$matches[1]}' AND (p.post_type = '{$matches[2]}' OR p.post_type = 'attachment')
						AND t.language_code='" . $current_language . "'";
		}
	}

	// filter calendar widget queries
	//elseif( preg_match("##", $sql, $matches) ){
	//
	//}

	return $sql;
}
add_filter( 'query', array( $this, 'filter_queries' ) );
