<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IFSR_Cron {
	const HOOK = 'ifsr_cleanup_expired_drafts';

	/** @var IFSR_DB */
	private $db;

	public function __construct( IFSR_DB $db ) {
		$this->db = $db;
	}

	public function register_hooks() {
		add_action( self::HOOK, array( $this, 'run_cleanup' ) );
	}

	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::HOOK );
		}
	}

	public function unschedule_cleanup() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	public function run_cleanup() {
		$this->db->purge_expired();
	}
}
