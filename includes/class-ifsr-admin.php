<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IFSR_Admin {
	/** @var IFSR_DB */
	private $db;

	public function __construct( IFSR_DB $db ) {
		$this->db = $db;
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_ifsr_delete_submission', array( $this, 'handle_delete_submission' ) );
		add_action( 'admin_post_ifsr_resend_link', array( $this, 'handle_resend_link' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Invisible Form Save & Resume', 'invisible-form-save-resume' ),
			__( 'Form Save & Resume', 'invisible-form-save-resume' ),
			'manage_options',
			'ifsr-admin',
			array( $this, 'render_admin_page' ),
			'dashicons-backup',
			56
		);
	}

	public function register_settings() {
		register_setting( 'ifsr_settings', 'ifsr_autosave_frequency', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'ifsr_settings', 'ifsr_expiry_hours', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'ifsr_settings', 'ifsr_retention_days', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'ifsr_settings', 'ifsr_require_consent', array( 'sanitize_callback' => 'absint' ) );
	}

	public function render_admin_page() {
		$entries = $this->db->get_abandoned_submissions( 100 );
		include IFSR_PLUGIN_DIR . 'templates/admin-page.php';
	}

	public function handle_delete_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'invisible-form-save-resume' ) );
		}
		check_admin_referer( 'ifsr_admin_action', 'ifsr_nonce' );
		$submission_id = absint( $_POST['submission_id'] ?? 0 );
		if ( $submission_id ) {
			$this->db->delete_submission( $submission_id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=ifsr-admin' ) );
		exit;
	}

	public function handle_resend_link() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'invisible-form-save-resume' ) );
		}
		check_admin_referer( 'ifsr_admin_action', 'ifsr_nonce' );
		$submission_id = absint( $_POST['submission_id'] ?? 0 );
		$submission    = $this->db->get_submission_by_id( $submission_id );
		if ( $submission ) {
			$raw_token  = IFSR_Token::generate_raw();
			$token_hash = IFSR_Token::hash( $raw_token );
			$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . absint( get_option( 'ifsr_expiry_hours', 72 ) ) . ' hours' ) );
			$this->db->store_token( $submission->id, $token_hash, $expires_at );
			$link = add_query_arg( 'ifsr_resume', rawurlencode( $raw_token ), home_url( '/' ) );
			wp_mail(
				$submission->email,
				sanitize_text_field( get_option( 'ifsr_resume_email_subject', __( 'Resume your form', 'invisible-form-save-resume' ) ) ),
				sprintf( __( 'Resume your form here: %s', 'invisible-form-save-resume' ), esc_url_raw( $link ) )
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=ifsr-admin' ) );
		exit;
	}
}
