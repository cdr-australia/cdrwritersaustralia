<?php
/**
 * Enquiry form handler.
 * Receives the enquiry-form pattern, saves each enquiry privately and emails it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Private list of enquiries in the admin menu. Not public on the site. */
add_action( 'init', function () {
	register_post_type(
		'cdrwa_enquiry',
		array(
			'labels'          => array(
				'name'          => 'Enquiries',
				'singular_name' => 'Enquiry',
				'menu_name'     => 'Enquiries',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-email-alt',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
} );

add_action( 'rest_api_init', function () {
	register_rest_route(
		'cdrwa/v1',
		'/enquiry',
		array(
			'methods'             => 'POST',
			'callback'            => 'cdrwa_handle_enquiry',
			'permission_callback' => '__return_true',
		)
	);
} );

function cdrwa_handle_enquiry( WP_REST_Request $request ) {
	$p = $request->get_params();

	/* Trap field: a person never fills it in. Answer as if it worked and store nothing. */
	if ( ! empty( $p['website'] ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* Rate limit: 5 enquiries per hour for each visitor address. */
	$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key   = 'cdrwa_enq_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 5 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'rate_limited' ), 429 );
	}

	$name    = isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '';
	$email   = isset( $p['email'] ) ? sanitize_email( $p['email'] ) : '';
	$phone   = isset( $p['phone'] ) ? sanitize_text_field( $p['phone'] ) : '';
	$country = isset( $p['country'] ) ? sanitize_text_field( $p['country'] ) : '';
	$topic   = isset( $p['topic'] ) ? sanitize_text_field( $p['topic'] ) : '';
	$message = isset( $p['message'] ) ? sanitize_textarea_field( $p['message'] ) : '';
	$consent = ! empty( $p['consent'] );

	$errors = array();
	if ( '' === $name || strlen( $name ) > 100 ) {
		$errors['name'] = 'Please enter your name.';
	}
	if ( ! is_email( $email ) ) {
		$errors['email'] = 'Please enter a valid email address.';
	}
	if ( '' !== $phone && ! preg_match( '/^[0-9+()\-.\s]{6,20}$/', $phone ) ) {
		$errors['phone'] = 'Please enter a valid phone number.';
	}
	if ( '' === $topic || strlen( $topic ) > 100 ) {
		$errors['topic'] = 'Please choose what you need help with.';
	}
	if ( strlen( $message ) < 10 || strlen( $message ) > 5000 ) {
		$errors['message'] = 'Please write a message of 10 to 5000 characters.';
	}
	if ( ! $consent ) {
		$errors['consent'] = 'Please tick the box so we can contact you.';
	}
	if ( $errors ) {
		return new WP_REST_Response( array( 'ok' => false, 'errors' => $errors ), 422 );
	}

	/* Save privately, so nothing is lost even if email fails. */
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'cdrwa_enquiry',
			'post_status'  => 'private',
			'post_title'   => wp_strip_all_tags( $topic . ' - ' . $name ),
			'post_content' => $message,
			'meta_input'   => array(
				'cdrwa_name'    => $name,
				'cdrwa_email'   => $email,
				'cdrwa_phone'   => $phone,
				'cdrwa_country' => $country,
				'cdrwa_topic'   => $topic,
			),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'save_failed' ), 500 );
	}

	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	/* Email the admin. Failure here never loses the enquiry. */
	$to      = apply_filters( 'cdrwa_enquiry_recipient', get_option( 'admin_email' ) );
	$body    = "Name: $name\nEmail: $email\nPhone: $phone\nCountry: $country\nTopic: $topic\n\n$message\n";
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );
	wp_mail( $to, 'New enquiry: ' . $topic, $body, $headers );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}