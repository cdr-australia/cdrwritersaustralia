<?php
/**
 * CDR Writers Australia block theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Front end: the same CSS and JS as the static site. */
add_action( 'wp_enqueue_scripts', function () {
	$uri = get_template_directory_uri() . '/assets';
	$ver = wp_get_theme()->get( 'Version' );

	$prev = array();
	foreach ( array( 'tokens', 'base', 'app-shell', 'sections', 'wp' ) as $name ) {
		$handle = 'cdrwa-' . $name;
		wp_enqueue_style( $handle, "$uri/css/$name.css", $prev, $ver );
		$prev = array( $handle );
	}

	$args = array(
		'strategy'  => 'defer',
		'in_footer' => true,
	);
	wp_enqueue_script( 'cdrwa-nav', "$uri/js/nav.js", array(), $ver, $args );
	wp_enqueue_script( 'cdrwa-faq', "$uri/js/faq.js", array(), $ver, $args );
} );

/* Editor: show the same look inside the block editor. */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'editor-styles' );
	add_editor_style(
		array(
			'assets/css/tokens.css',
			'assets/css/base.css',
			'assets/css/sections.css',
			'assets/css/wp.css',
		)
	);
} );

/* Theme colour for mobile browsers. */
add_action( 'wp_head', function () {
	echo '<meta name="theme-color" content="#0b2a4a">' . "\n";
}, 1 );

/* Not needed: modern browsers render emoji natively. */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
/* Pattern category shown in the block inserter. */
add_action( 'init', function () {
	register_block_pattern_category(
		'cdrwa-sections',
		array( 'label' => __( 'CDR Writers sections', 'cdrwa' ) )
	);
} );
/* Enquiry form handler (see inc/enquiry.php). */
require_once get_theme_file_path( 'inc/enquiry.php' );

/* A Query Loop whose post template has the class "cdrwa-related" leaves out the guide being read. */
add_filter( 'query_loop_block_query_vars', function ( $query, $block ) {
	$class = ( isset( $block->parsed_block['attrs']['className'] ) ) ? (string) $block->parsed_block['attrs']['className'] : '';
	if ( false !== strpos( $class, 'cdrwa-related' ) && is_singular( 'post' ) ) {
		$exclude               = isset( $query['post__not_in'] ) ? (array) $query['post__not_in'] : array();
		$exclude[]             = get_queried_object_id();
		$query['post__not_in'] = $exclude;
	}
	return $query;
}, 10, 2 );