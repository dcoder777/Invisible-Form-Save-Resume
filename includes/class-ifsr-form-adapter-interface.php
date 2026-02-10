<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface IFSR_Form_Adapter_Interface {
	public function get_name();
	public function can_handle_context( $context );
	public function normalize_payload( $payload );
}
