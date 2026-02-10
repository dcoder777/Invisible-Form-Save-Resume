<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IFSR_DB {
	public function get_submissions_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ifsr_submissions';
	}

	public function get_tokens_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ifsr_tokens';
	}

	public function maybe_create_tables() {
		if ( get_option( 'ifsr_tables_installed' ) !== IFSR_PLUGIN_VERSION ) {
			$this->create_tables();
		}
	}

	public function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$submissions     = $this->get_submissions_table_name();
		$tokens          = $this->get_tokens_table_name();

		$sql_submissions = "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_fingerprint varchar(191) NOT NULL,
			source varchar(100) NOT NULL DEFAULT 'native',
			email varchar(190) NOT NULL,
			consent tinyint(1) NOT NULL DEFAULT 0,
			state longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY form_fingerprint (form_fingerprint),
			KEY email (email),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		$sql_tokens = "CREATE TABLE {$tokens} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) unsigned NOT NULL,
			token_hash varchar(255) NOT NULL,
			expires_at datetime NOT NULL,
			used_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY token_hash (token_hash(191)),
			KEY submission_id (submission_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_tokens );

		update_option( 'ifsr_tables_installed', IFSR_PLUGIN_VERSION );
	}

	public function upsert_submission( $form_fingerprint, $source, $email, $state, $consent ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Valid email is required to resume form later.', 'invisible-form-save-resume' ) );
		}

		$table       = $this->get_submissions_table_name();
		$now         = current_time( 'mysql', 1 );
		$expiry      = gmdate( 'Y-m-d H:i:s', strtotime( '+' . absint( get_option( 'ifsr_expiry_hours', 72 ) ) . ' hours' ) );
		$serialized  = wp_json_encode( $state );
		$form_key    = sanitize_text_field( $form_fingerprint );
		$source_name = sanitize_key( $source );

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE form_fingerprint = %s AND email = %s LIMIT 1",
				$form_key,
				$email
			)
		);

		if ( $existing ) {
			$updated = $wpdb->update(
				$table,
				array(
					'source'     => $source_name,
					'state'      => $serialized,
					'consent'    => absint( $consent ),
					'updated_at' => $now,
					'expires_at' => $expiry,
				),
				array( 'id' => absint( $existing->id ) ),
				array( '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new WP_Error( 'db_update_failed', __( 'Could not update saved form state.', 'invisible-form-save-resume' ) );
			}

			return absint( $existing->id );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'form_fingerprint' => $form_key,
				'source'           => $source_name,
				'email'            => $email,
				'consent'          => absint( $consent ),
				'state'            => $serialized,
				'created_at'       => $now,
				'updated_at'       => $now,
				'expires_at'       => $expiry,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_insert_failed', __( 'Could not save form state.', 'invisible-form-save-resume' ) );
		}

		return absint( $wpdb->insert_id );
	}

	public function get_submission_by_id( $submission_id ) {
		global $wpdb;
		$table = $this->get_submissions_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $submission_id ) ) );
		if ( ! $row ) {
			return null;
		}
		$row->state = json_decode( $row->state, true );
		return $row;
	}

	public function get_submission_by_token_hash( $token_hash ) {
		global $wpdb;
		$tokens      = $this->get_tokens_table_name();
		$submissions = $this->get_submissions_table_name();
		$sql         = "SELECT s.* FROM {$tokens} t INNER JOIN {$submissions} s ON s.id = t.submission_id WHERE t.token_hash = %s AND t.expires_at >= UTC_TIMESTAMP() AND t.used_at IS NULL LIMIT 1";
		$row         = $wpdb->get_row( $wpdb->prepare( $sql, $token_hash ) );
		if ( ! $row ) {
			return null;
		}
		$row->state = json_decode( $row->state, true );
		return $row;
	}

	public function store_token( $submission_id, $token_hash, $expires_at ) {
		global $wpdb;
		$table = $this->get_tokens_table_name();
		return $wpdb->insert(
			$table,
			array(
				'submission_id' => absint( $submission_id ),
				'token_hash'    => sanitize_text_field( $token_hash ),
				'expires_at'    => $expires_at,
				'created_at'    => current_time( 'mysql', 1 ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	public function mark_token_used( $token_hash ) {
		global $wpdb;
		$table = $this->get_tokens_table_name();
		return $wpdb->update(
			$table,
			array( 'used_at' => current_time( 'mysql', 1 ) ),
			array( 'token_hash' => sanitize_text_field( $token_hash ) ),
			array( '%s' ),
			array( '%s' )
		);
	}

	public function delete_submission( $submission_id ) {
		global $wpdb;
		$submission_id = absint( $submission_id );
		$wpdb->delete( $this->get_tokens_table_name(), array( 'submission_id' => $submission_id ), array( '%d' ) );
		return $wpdb->delete( $this->get_submissions_table_name(), array( 'id' => $submission_id ), array( '%d' ) );
	}

	public function get_abandoned_submissions( $limit = 50 ) {
		global $wpdb;
		$table = $this->get_submissions_table_name();
		$limit = max( 1, absint( $limit ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d", $limit ) );
	}

	public function purge_expired() {
		global $wpdb;
		$submissions = $this->get_submissions_table_name();
		$tokens      = $this->get_tokens_table_name();
		$retention   = absint( get_option( 'ifsr_retention_days', 30 ) );
		$retention_clause = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $retention . ' days' ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$tokens} WHERE expires_at < UTC_TIMESTAMP() OR created_at < %s", $retention_clause ) );
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$submissions} WHERE expires_at < UTC_TIMESTAMP() OR updated_at < %s", $retention_clause ) );
	}
}
