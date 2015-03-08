<?php

function edd_wpzoo_query_vars( $query_vars ){
	var_dump($query_vars);
	return $query_vars;
}
//add_filter( 'query_vars', 'edd_wpzoo_query_vars' );

function edd_wpzoo_request( $query_vars ){
	var_dump($query_vars);
	if ( $query_vars['pagename'] == 'blog' ){
		$query_vars['pagename'] = $query_vars['pagename'] . '-' . $query_vars['lang'];
	}
	var_dump($query_vars);
	return $query_vars;
}
//add_filter( 'request', 'edd_wpzoo_request' );

function edd_wpzoo_request_2( $query_vars ){
	var_dump($query_vars);
	if ( isset( $query_vars['lang'] ) ){
		$query_vars['pagename'] = $query_vars['pagename'] . '@' . $query_vars['lang'];
	}
	var_dump($query_vars);
	return $query_vars;
}
//add_filter( 'request', 'edd_wpzoo_request_2' );

function polylang_wpzoo_post_link( $permalink, $post, $leavename ) {
	$lang = pll_get_post_language($post->ID);
	$new_permalink = str_replace( $post->post_name . '@' . $lang, $post->post_name, $permalink );
	if ( $new_permalink ) {
		$permalink = $new_permalink;
	}
	return $permalink;
}
//add_filters( 'post_link', 'polylang_wpzoo_post_link' 10, 3 )

function your_function_name($query) {
	if ( isset( $query['query_vars']['lang'] ) ) {
		$query->set('tax_query', array(
			array(
				'taxonomy' => 'language',
				'field'    => 'slug',
				'terms'    => $query['query_vars']['lang'],
			),
		) );
	}
}
//add_action( 'pre_get_posts', 'your_function_name' );
