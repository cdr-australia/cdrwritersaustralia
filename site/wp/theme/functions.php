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