<?php
/**
 * Seeds demo content for the local docker test stack.
 * Usage: wp eval-file tests/docker/seed.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$strong = <<<HTML
<h2>Why local end-to-end testing matters</h2>
<p>Testing a WordPress SEO plugin end to end means exercising every layer at once: the on-page analyzer that scores content, the schema generator that emits JSON-LD, the XML sitemap that search engines consume, and the AI assistants that draft titles and meta descriptions. A local docker environment makes this repeatable and safe.</p>
<h3>What a good workflow looks like</h3>
<p>Start the stack, seed realistic content, activate a license, then walk each admin screen. The SEO score should reflect the actual content quality: a well-structured article with headings, internal links, images with alt text, and a focused keyphrase should score far higher than a bare paragraph.</p>
<ul>
<li>Score rings animate from zero on every screen that shows them</li>
<li>AI tasks deduct credits and the dashboard reflects the balance instantly</li>
<li>Sitemaps, redirects, and schema validate against real consumers</li>
</ul>
<p>When the whole pipeline passes locally, the same build can be shipped to production with confidence. <a href="/">Return to the homepage</a> to see breadcrumbs and title templates in action.</p>
HTML;

$weak = <<<HTML
<p>hello this is a test post</p>
HTML;

$posted = array();

$existing = get_page_by_title( 'Local End-to-End Testing for WordPress SEO Plugins', OBJECT, 'post' );
if ( ! $existing ) {
	$posted['strong'] = wp_insert_post( array(
		'post_title'   => 'Local End-to-End Testing for WordPress SEO Plugins',
		'post_content' => $strong,
		'post_status'  => 'publish',
		'post_type'    => 'post',
	) );
	update_post_meta( $posted['strong'], '_seoistic_title', 'End-to-End Testing for WordPress SEO Plugins — The Complete Local Guide' );
	update_post_meta( $posted['strong'], '_seoistic_description', 'Run WordPress SEO plugins through full end-to-end tests locally: animated score rings, AI credits, schema, sitemaps, and redirects — all inside docker.' );
	update_post_meta( $posted['strong'], '_seoistic_keyphrase', 'wordpress seo plugin testing' );
} else {
	$posted['strong'] = $existing->ID;
}

$existing = get_page_by_title( 'Untitled draft ideas', OBJECT, 'post' );
if ( ! $existing ) {
	$posted['weak'] = wp_insert_post( array(
		'post_title'   => 'Untitled draft ideas',
		'post_content' => $weak,
		'post_status'  => 'publish',
		'post_type'    => 'post',
	) );
} else {
	$posted['weak'] = $existing->ID;
}

printf( "seeded: strong=%d weak=%d\n", (int) $posted['strong'], (int) $posted['weak'] );
