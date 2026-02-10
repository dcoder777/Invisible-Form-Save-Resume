<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IFSR_REST_Controller {
	/** @var IFSR_DB */
	private $db;

	public function __construct( IFSR_DB $db ) {
		$this->db = $db;
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_client' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ifsr/v1',
			'/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_form_state' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ifsr/v1',
			'/restore',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'restore_form_state' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'ifsr/v1',
			'/cleanup',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cleanup_expired' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public function enqueue_client() {
		wp_enqueue_script(
			'ifsr-client',
			IFSR_PLUGIN_URL . 'assets/js/ifsr-client.js',
			array(),
			IFSR_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'ifsr-client',
			'IFSRConfig',
			array(
				'endpoint'          => esc_url_raw( rest_url( 'ifsr/v1' ) ),
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'autosaveFrequency' => absint( get_option( 'ifsr_autosave_frequency', 8 ) ) * 1000,
				'requireConsent'    => (bool) get_option( 'ifsr_require_consent', 1 ),
				'resumeToken'       => IFSR_Token::get_resume_token_from_request(),
			)
		);
	}

	public function save_form_state( WP_REST_Request $request ) {
		$nonce_check = $this->assert_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$form_fingerprint = sanitize_text_field( $request->get_param( 'formFingerprint' ) );
		$email            = sanitize_email( $request->get_param( 'email' ) );
		$state            = $request->get_param( 'state' );
		$consent          = absint( $request->get_param( 'consent' ) );
		$source           = sanitize_key( $request->get_param( 'source' ) ?: 'native' );

		if ( empty( $form_fingerprint ) || empty( $email ) || empty( $state ) ) {
			return new WP_Error( 'missing_fields', __( 'Form fingerprint, email and state are required.', 'invisible-form-save-resume' ), array( 'status' => 400 ) );
		}

		if ( get_option( 'ifsr_require_consent', 1 ) && ! $consent ) {
			return new WP_Error( 'consent_required', __( 'Consent is required before auto-save can be used.', 'invisible-form-save-resume' ), array( 'status' => 400 ) );
		}

		$submission_id = $this->db->upsert_submission( $form_fingerprint, $source, $email, $state, $consent );
		if ( is_wp_error( $submission_id ) ) {
			return $submission_id;
		}

		$raw_token  = IFSR_Token::generate_raw();
		$token_hash = IFSR_Token::hash( $raw_token );
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . absint( get_option( 'ifsr_expiry_hours', 72 ) ) . ' hours' ) );
		$this->db->store_token( $submission_id, $token_hash, $expires_at );

		$resume_link = add_query_arg( 'ifsr_resume', rawurlencode( $raw_token ), home_url( '/' ) );
		$this->send_resume_email( $email, $resume_link, $expires_at );

		return rest_ensure_response(
			array(
				'success'    => true,
				'resumeLink' => $resume_link,
			)
		);
	}

	public function restore_form_state( WP_REST_Request $request ) {
		$raw_token = sanitize_text_field( $request->get_param( 'token' ) );
		if ( empty( $raw_token ) ) {
			return new WP_Error( 'missing_token', __( 'Token is required.', 'invisible-form-save-resume' ), array( 'status' => 400 ) );
		}

		$token_hash  = IFSR_Token::hash( $raw_token );
		$submission  = $this->db->get_submission_by_token_hash( $token_hash );
		if ( ! $submission ) {
			return new WP_Error( 'invalid_token', __( 'Invalid or expired token.', 'invisible-form-save-resume' ), array( 'status' => 404 ) );
		}

		$this->db->mark_token_used( $token_hash );
		return rest_ensure_response(
			array(
				'success'         => true,
				'formFingerprint' => $submission->form_fingerprint,
				'state'           => $submission->state,
			)
		);
	}

	public function cleanup_expired() {
		$deleted = $this->db->purge_expired();
		return rest_ensure_response( array( 'success' => true, 'deleted' => intval( $deleted ) ) );
	}

	private function send_resume_email( $email, $resume_link, $expires_at ) {
		$subject = sanitize_text_field( get_option( 'ifsr_resume_email_subject', __( 'Resume your form', 'invisible-form-save-resume' ) ) );
		$body    = sprintf(
			/* translators: 1: resume link, 2: expiry */
			__( "You can resume your form using this secure link:\n\n%s\n\nThis link expires on %s UTC.", 'invisible-form-save-resume' ),
			esc_url_raw( $resume_link ),
			esc_html( $expires_at )
		);
		wp_mail( $email, $subject, $body );
	}

	private function assert_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid security token.', 'invisible-form-save-resume' ), array( 'status' => 403 ) );
		}

		return true;
	}
}
