<div class="wrap">
	<h1><?php esc_html_e( 'Invisible Form Save & Resume', 'invisible-form-save-resume' ); ?></h1>

	<form method="post" action="options.php" style="max-width: 640px; margin-bottom: 24px;">
		<?php settings_fields( 'ifsr_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ifsr_autosave_frequency"><?php esc_html_e( 'Auto-save frequency (seconds)', 'invisible-form-save-resume' ); ?></label></th>
				<td><input name="ifsr_autosave_frequency" id="ifsr_autosave_frequency" type="number" min="3" value="<?php echo esc_attr( get_option( 'ifsr_autosave_frequency', 8 ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ifsr_expiry_hours"><?php esc_html_e( 'Resume link expiry (hours)', 'invisible-form-save-resume' ); ?></label></th>
				<td><input name="ifsr_expiry_hours" id="ifsr_expiry_hours" type="number" min="1" value="<?php echo esc_attr( get_option( 'ifsr_expiry_hours', 72 ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="ifsr_retention_days"><?php esc_html_e( 'Retention window (days)', 'invisible-form-save-resume' ); ?></label></th>
				<td><input name="ifsr_retention_days" id="ifsr_retention_days" type="number" min="1" value="<?php echo esc_attr( get_option( 'ifsr_retention_days', 30 ) ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Require consent checkbox', 'invisible-form-save-resume' ); ?></th>
				<td><label><input name="ifsr_require_consent" type="checkbox" value="1" <?php checked( 1, get_option( 'ifsr_require_consent', 1 ) ); ?> /> <?php esc_html_e( 'Require explicit consent before saving draft data', 'invisible-form-save-resume' ); ?></label></td>
			</tr>
		</table>
		<?php submit_button( __( 'Save Settings', 'invisible-form-save-resume' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Abandoned Entries', 'invisible-form-save-resume' ); ?></h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'invisible-form-save-resume' ); ?></th>
				<th><?php esc_html_e( 'Form', 'invisible-form-save-resume' ); ?></th>
				<th><?php esc_html_e( 'Email', 'invisible-form-save-resume' ); ?></th>
				<th><?php esc_html_e( 'Updated', 'invisible-form-save-resume' ); ?></th>
				<th><?php esc_html_e( 'Expires', 'invisible-form-save-resume' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'invisible-form-save-resume' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $entries ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No abandoned entries found.', 'invisible-form-save-resume' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry->id ); ?></td>
						<td><?php echo esc_html( $entry->form_fingerprint ); ?></td>
						<td><?php echo esc_html( $entry->email ); ?></td>
						<td><?php echo esc_html( $entry->updated_at ); ?></td>
						<td><?php echo esc_html( $entry->expires_at ); ?></td>
						<td style="display:flex;gap:8px;">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="ifsr_resend_link" />
								<input type="hidden" name="submission_id" value="<?php echo esc_attr( $entry->id ); ?>" />
								<?php wp_nonce_field( 'ifsr_admin_action', 'ifsr_nonce' ); ?>
								<button class="button"><?php esc_html_e( 'Resend Link', 'invisible-form-save-resume' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="ifsr_delete_submission" />
								<input type="hidden" name="submission_id" value="<?php echo esc_attr( $entry->id ); ?>" />
								<?php wp_nonce_field( 'ifsr_admin_action', 'ifsr_nonce' ); ?>
								<button class="button button-link-delete"><?php esc_html_e( 'Delete', 'invisible-form-save-resume' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
