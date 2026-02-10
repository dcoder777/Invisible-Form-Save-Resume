<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IFSR_Adapter_Native_HTML implements IFSR_Form_Adapter_Interface {
	public function get_name() {
		return 'native';
	}

	public function can_handle_context( $context ) {
		return true;
	}

	public function normalize_payload( $payload ) {
		$state = array();
		if ( ! is_array( $payload ) ) {
			return $state;
		}
		foreach ( $payload as $key => $value ) {
			$field          = sanitize_key( $key );
			$state[ $field ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
		}
		return $state;
	}
}
