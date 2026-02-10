<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IFSR_Token {
	public static function generate_raw() {
		return wp_generate_password( 48, false, false ) . wp_rand();
	}

	public static function hash( $raw_token ) {
		return hash_hmac( 'sha256', $raw_token, wp_salt( 'auth' ) );
	}

	public static function build_resume_url( $raw_token ) {
		return add_query_arg(
			array( 'ifsr_resume' => rawurlencode( $raw_token ) ),
			home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) )
		);
	}

	public static function get_resume_token_from_request() {
		if ( empty( $_GET['ifsr_resume'] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_GET['ifsr_resume'] ) );
	}
}
