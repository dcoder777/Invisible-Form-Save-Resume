<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IFSR_Adapter_Gutenberg implements IFSR_Form_Adapter_Interface {
	public function get_name() {
		return 'gutenberg';
	}

	public function can_handle_context( $context ) {
		return ! empty( $context['is_block_editor_form'] );
	}

	public function normalize_payload( $payload ) {
		$state = array();
		if ( ! is_array( $payload ) ) {
			return $state;
		}
		foreach ( $payload as $key => $value ) {
			$state[ sanitize_text_field( $key ) ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
		}
		return $state;
	}
}
