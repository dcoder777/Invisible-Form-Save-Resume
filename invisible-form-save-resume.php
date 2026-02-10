<?php
/**
 * Plugin Name: Invisible Form Save & Resume
 * Description: Auto-save in-progress forms and resume with secure magic links.
 * Version: 0.1.0
 * Author: Codex
 * Text Domain: invisible-form-save-resume
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IFSR_PLUGIN_VERSION', '0.1.0' );
define( 'IFSR_PLUGIN_FILE', __FILE__ );
define( 'IFSR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IFSR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-db.php';
require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-token.php';
require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-form-adapter-interface.php';
require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-adapter-native-html.php';
require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-adapter-gutenberg.php';
require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-rest-controller.php';
require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-cron.php';
require_once IFSR_PLUGIN_DIR . 'includes/class-ifsr-admin.php';

class IFSR_Plugin {

	/** @var IFSR_DB */
	private $db;

	/** @var IFSR_Admin */
	private $admin;

	/** @var IFSR_REST_Controller */
	private $rest;

	/** @var IFSR_Cron */
	private $cron;

	/** @var IFSR_Form_Adapter_Interface[] */
	private $adapters = array();

	public function __construct() {
		$this->db   = new IFSR_DB();
		$this->rest = new IFSR_REST_Controller( $this->db );
		$this->cron = new IFSR_Cron( $this->db );
		$this->admin = new IFSR_Admin( $this->db );

		$this->register_default_options();
		$this->register_default_adapters();

		add_action( 'init', array( $this, 'bootstrap' ) );
		register_activation_hook( IFSR_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( IFSR_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	public function register_default_options() {
		add_option( 'ifsr_autosave_frequency', 8 );
		add_option( 'ifsr_expiry_hours', 72 );
		add_option( 'ifsr_retention_days', 30 );
		add_option( 'ifsr_require_consent', 1 );
		add_option( 'ifsr_resume_email_subject', __( 'Resume your form', 'invisible-form-save-resume' ) );
	}

	public function register_default_adapters() {
		$this->adapters['native']    = new IFSR_Adapter_Native_HTML();
		$this->adapters['gutenberg'] = new IFSR_Adapter_Gutenberg();
	}

	public function bootstrap() {
		$this->db->maybe_create_tables();
		$this->rest->register_routes();
		$this->admin->register_hooks();
		$this->cron->register_hooks();

		/**
		 * Let third-party form plugins register their own adapters.
		 */
		$this->adapters = apply_filters( 'ifsr_form_adapters', $this->adapters );
	}

	public function activate() {
		$this->db->create_tables();
		$this->cron->schedule_cleanup();
	}

	public function deactivate() {
		$this->cron->unschedule_cleanup();
	}
}

new IFSR_Plugin();
