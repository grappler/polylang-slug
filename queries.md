# WordPress Queries

Here is the list of SQL queries that WordPress makes to get the post from the database. We need to understand them so that we can extend it to fetch the post for the current language.

## Front page - Polylang handles this for us.
## Blog page - The query is the same as for a page.

## Posts
### `/%year%/%monthnum%/%day%/%postname%/`
`#SELECT   wp_posts.* FROM wp_posts  WHERE 1=1  AND ( 
  ( YEAR( wp_posts.post_date ) = 2015 AND MONTH( wp_posts.post_date ) = 12 AND DAYOFMONTH( wp_posts.post_date ) = 24 )
) AND wp_posts.post_name = 'hello-world' AND wp_posts.post_type = 'post'  ORDER BY wp_posts.post_date DESC #`

### `/%year%/%monthnum%/%postname%/`
`#SELECT   wp_posts.* FROM wp_posts  WHERE 1=1  AND ( 
  ( YEAR( wp_posts.post_date ) = 2015 AND MONTH( wp_posts.post_date ) = 12 )
) AND wp_posts.post_name = 'hello-world' AND wp_posts.post_type = 'post'  ORDER BY wp_posts.post_date DESC #`

### `/%postname%/`
`#SELECT   wp_posts.* FROM wp_posts  WHERE 1=1  AND wp_posts.post_name = 'hello-world' AND wp_posts.post_type = 'post'  ORDER BY wp_posts.post_date DESC #`

## Pages / Attachments
`#
		SELECT ID, post_name, post_parent, post_type
		FROM wp_posts
		WHERE post_name IN ('sample-page')
		AND post_type IN ('page','attachment')
	#`

## CPT Posts
`## SELECT   wp_posts.* FROM wp_posts  WHERE 1=1  AND wp_posts.post_name = 'post-type-posts' AND wp_posts.post_type = 'post_type_posts'  ORDER BY wp_posts.post_date DESC #`

## CPT Pages
`#
		SELECT ID, post_name, post_parent, post_type
		FROM wp_posts
		WHERE post_name IN ('post-type-pages')
		AND post_type IN ('post_type_pages','attachment')
	#`

## Archive pages
### Category
`#
					SELECT wp_term_taxonomy.term_id
					FROM wp_term_taxonomy
					INNER JOIN wp_terms USING (term_id)
					WHERE taxonomy = 'category'
					AND wp_terms.slug IN ('allgemein')
				#`
### Tag
`#
					SELECT wp_term_taxonomy.term_taxonomy_id
					FROM wp_term_taxonomy
					INNER JOIN wp_terms USING (term_id)
					WHERE taxonomy = 'language'
					AND wp_terms.slug IN ('en')
				#`
`#
					SELECT wp_term_taxonomy.term_taxonomy_id
					FROM wp_term_taxonomy
					INNER JOIN wp_terms USING (term_id)
					WHERE taxonomy = 'post_tag'
					AND wp_terms.slug IN ('tag')
				#`