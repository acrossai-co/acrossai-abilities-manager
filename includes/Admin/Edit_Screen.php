<?php
/**
 * Edit screen rendering and actions.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Admin;

use AcrossAI_Abilities_Manager\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the ability override edit form: rendering, saving, deleting, and toggling.
 *
 * All public action handlers (save, delete, toggle_allowed) verify the current
 * user capability and check a WordPress nonce before touching the database.
 * The render() method produces the HTML form and its inline JavaScript.
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Edit_Screen {

	/**
	 * Routes POST and GET action requests originating from the plugin's admin page.
	 *
	 * Runs on `admin_init`. The method first checks the `page` parameter so
	 * it exits immediately for any request that is not targeting this plugin,
	 * then dispatches to the appropriate handler based on `aam_action`.
	 *
	 * @return void
	 */
	public static function handle_actions(): void {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Bail immediately — this request is not for the Abilities Manager page.
		if ( 'acrossai-abilities-manager' !== $page ) {
			return;
		}

		$action = isset( $_REQUEST['aam_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['aam_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Save is restricted to POST requests to prevent CSRF via link-following.
		if ( 'save' === $action && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			self::save();
		}

		// Delete and toggle_allowed use GET requests with nonce verification.
		if ( 'delete' === $action ) {
			self::delete();
		}

		if ( 'toggle_allowed' === $action ) {
			self::toggle_allowed();
		}
	}

	/**
	 * Renders the ability override edit form.
	 *
	 * Loads the WP_Ability object (if registered) and the stored override row
	 * (if any), then builds the resolved display values from both sources.
	 *
	 * If the stored override row turns out to be a perfect no-op (all fields
	 * match the live ability defaults), it is deleted automatically so the
	 * database stays clean. The form is then rendered with the live defaults.
	 *
	 * @param string $slug Ability slug to edit.
	 * @return void
	 */
	public static function render( string $slug ): void {
		$slug     = sanitize_text_field( $slug );
		$ability  = function_exists( 'wp_get_ability' ) ? wp_get_ability( $slug ) : null;
		$override = Repository::get_by_slug( $slug );

		// Neither the ability nor an override exists — nothing to display.
		if ( ! $ability && ! $override ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Ability not found.', 'acrossai-abilities-manager' ) . '</p></div>';
			return;
		}

		$provider      = is_array( $override ) && ! empty( $override['provider'] ) ? (string) $override['provider'] : self::detect_provider( $slug );
		$category_slug = $ability instanceof \WP_Ability ? $ability->get_category() : '';
		$category      = self::ability_category_label( $category_slug );
		$values        = self::resolved_values( $override, $ability );

		// Auto-clean stale override rows: if every saved value already matches
		// the live ability's defaults, the row is redundant and is removed now.
		if ( is_array( $override ) && $ability instanceof \WP_Ability && array() === self::build_override_values( $values, $ability ) ) {
			Repository::delete( $slug );
			$override      = null;
			$provider      = self::detect_provider( $slug );
			$category_slug = $ability instanceof \WP_Ability ? $ability->get_category() : '';
			$category      = self::ability_category_label( $category_slug );
			$values        = self::resolved_values( null, $ability );
		}

		$back_url = admin_url( 'tools.php?page=acrossai-abilities-manager' );
		?>
		<p>
			<a href="<?php echo esc_url( $back_url ); ?>" class="button"><?php esc_html_e( 'Back to List', 'acrossai-abilities-manager' ); ?></a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=acrossai-abilities-manager' ) ); ?>">
			<input type="hidden" name="page" value="acrossai-abilities-manager" />
			<input type="hidden" name="aam_action" value="save" />
			<input type="hidden" name="slug" value="<?php echo esc_attr( $slug ); ?>" />
			<?php wp_nonce_field( 'aam_save_meta_' . $slug, 'aam_meta_nonce' ); ?>
			<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row"><?php esc_html_e( 'Ability Slug', 'acrossai-abilities-manager' ); ?></th><td><input type="text" class="regular-text" value="<?php echo esc_attr( $slug ); ?>" readonly /></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Provider', 'acrossai-abilities-manager' ); ?></th><td><input type="text" class="regular-text" value="<?php echo esc_attr( $provider ); ?>" readonly /></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Category', 'acrossai-abilities-manager' ); ?></th><td><?php echo wp_kses_post( self::render_category_value( $category, $category_slug ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Allowed on Site', 'acrossai-abilities-manager' ); ?></th><td><label><input type="checkbox" name="site_allowed" value="1" <?php checked( (bool) $values['site_allowed'] ); ?> /> <?php esc_html_e( 'Allow this ability to run on this site.', 'acrossai-abilities-manager' ); ?></label></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Readonly', 'acrossai-abilities-manager' ); ?></th><td><?php self::select( 'readonly', $values['readonly'], false ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Destructive', 'acrossai-abilities-manager' ); ?></th><td><?php self::select( 'destructive', $values['destructive'], false ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Idempotent', 'acrossai-abilities-manager' ); ?></th><td><?php self::select( 'idempotent', $values['idempotent'], false ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Show in REST', 'acrossai-abilities-manager' ); ?></th><td><label><input type="checkbox" name="show_in_rest" value="1" <?php checked( (bool) $values['show_in_rest'] ); ?> /> <?php esc_html_e( 'Expose in REST.', 'acrossai-abilities-manager' ); ?></label></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'MCP Public', 'acrossai-abilities-manager' ); ?></th><td><label><input type="checkbox" id="aam-mcp-public" name="mcp_public" value="1" <?php checked( (bool) $values['mcp_public'] ); ?> /> <?php esc_html_e( 'Expose publicly to MCP clients.', 'acrossai-abilities-manager' ); ?></label></td></tr>
			<tr id="aam-mcp-type-row"><th scope="row"><?php esc_html_e( 'MCP Type', 'acrossai-abilities-manager' ); ?></th><td><?php self::mcp_type_select( (string) $values['mcp_type'], false ); ?></td></tr>
			</tbody></table>
			<p class="submit">
					<button type="submit" name="aam_save_target" value="stay" class="button button-primary"><?php esc_html_e( 'Save', 'acrossai-abilities-manager' ); ?></button>
					<button type="submit" name="aam_save_target" value="exit" class="button"><?php esc_html_e( 'Save and Exit', 'acrossai-abilities-manager' ); ?></button>
					<?php if ( is_array( $override ) ) : ?>
						<?php
						// Only show the Reset button when there is actually a stored override to remove.
						$delete_url = wp_nonce_url(
							add_query_arg(
								array(
									'page'       => 'acrossai-abilities-manager',
									'aam_action' => 'delete',
									'slug'       => $slug,
								),
								admin_url( 'tools.php' )
							),
							'aam_delete_meta_' . $slug,
							'aam_delete_nonce'
						);
						?>
						<a href="<?php echo esc_url( $delete_url ); ?>" class="button" onclick="return window.confirm(<?php echo esc_attr( wp_json_encode( __( 'Reset this override?', 'acrossai-abilities-manager' ) ) ); ?>);"><?php esc_html_e( 'Reset Override', 'acrossai-abilities-manager' ); ?></a>
					<?php endif; ?>
				</p>
		</form>
		<script>
		document.addEventListener( 'DOMContentLoaded', function() {
			var mcpPublic = document.getElementById( 'aam-mcp-public' );
			var mcpTypeRow = document.getElementById( 'aam-mcp-type-row' );

			// Guard: both elements must exist before attaching the visibility logic.
			if ( ! mcpPublic || ! mcpTypeRow ) {
				return;
			}

			// Show the MCP Type row only when the MCP Public checkbox is checked,
			// since the type setting is meaningless when the ability is not public.
			function syncMcpTypeVisibility() {
				mcpTypeRow.style.display = mcpPublic.checked ? '' : 'none';
			}

			mcpPublic.addEventListener( 'change', syncMcpTypeVisibility );
			// Run once on load to match the initial checkbox state.
			syncMcpTypeVisibility();
		} );
		</script>
		<?php
	}

	/**
	 * Handles the save form submission for an ability override.
	 *
	 * Verifies capability and nonce, builds a diff-only override payload
	 * (only fields that differ from the live ability defaults are persisted),
	 * and upserts the result into the database. If the diff is empty, any
	 * existing override is deleted and the user is redirected with a notice.
	 *
	 * @return void
	 */
	public static function save(): void {
		// Only users with manage_options may create or update overrides.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit overrides.', 'acrossai-abilities-manager' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		check_admin_referer( 'aam_save_meta_' . $slug, 'aam_meta_nonce' );

		$ability           = function_exists( 'wp_get_ability' ) ? wp_get_ability( $slug ) : null;
		$existing_override = Repository::get_by_slug( $slug );
		$existing_row      = is_array( $existing_override );
		$save_target       = self::save_target();
		$submitted         = self::submitted_values();
		$override          = self::build_override_values( $submitted, $ability );
		$override          = self::prepare_override_for_save( $override, $existing_override );

		// The submitted form values produce no diff against the live defaults.
		if ( array() === $override ) {
			// If a stale override row already exists, clean it up now.
			if ( $existing_row ) {
				Repository::delete( $slug );
				self::redirect_after_save( $slug, 'deleted', $save_target );
			}

			// Redirect with a "no change" notice rather than writing an empty row.
			self::redirect_after_save( $slug, 'noop', $save_target );
		}

		$result = Repository::upsert( $slug, $override );
		self::redirect_after_save( $slug, $result ? 'saved' : 'error', $save_target );
	}


	/**
	 * Handles the delete (reset) action for an ability override.
	 *
	 * Verifies capability and a per-slug nonce before deleting the row.
	 * Always redirects back to the list view with a success or error notice.
	 *
	 * @return void
	 */
	public static function delete(): void {
		// Only users with manage_options may delete overrides.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to reset overrides.', 'acrossai-abilities-manager' ) );
		}
		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'aam_delete_meta_' . $slug, 'aam_delete_nonce' );
		$deleted = Repository::delete( $slug );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'acrossai-abilities-manager',
					'aam_notice' => $deleted ? 'deleted' : 'error',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Toggles the site_allowed flag for an ability override.
	 *
	 * Verifies capability and a per-slug nonce, then builds or updates the
	 * override so only `site_allowed` changes. If the resulting diff is empty
	 * (e.g. the ability was already in the desired state), any existing override
	 * row is deleted and the user is redirected with a notice.
	 *
	 * @return void
	 */
	public static function toggle_allowed(): void {
		// Only users with manage_options may change site-level ability availability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change ability availability.', 'acrossai-abilities-manager' ) );
		}

		$slug  = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allow = isset( $_GET['site_allowed'] ) && '1' === sanitize_key( wp_unslash( $_GET['site_allowed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'aam_toggle_allowed_' . $slug, 'aam_toggle_allowed_nonce' );

		$ability                   = function_exists( 'wp_get_ability' ) ? wp_get_ability( $slug ) : null;
		$existing_override         = Repository::get_by_slug( $slug );
		$submitted                 = self::resolved_values( $existing_override, $ability );
		$submitted['site_allowed'] = $allow;
		$override                  = self::build_override_values( $submitted, $ability );
		$override                  = self::prepare_override_for_save( $override, $existing_override );

		// The toggle resulted in no diff (ability was already in the desired state).
		if ( array() === $override ) {
			// If a row exists but no longer carries meaningful data, remove it.
			if ( is_array( $existing_override ) ) {
				Repository::delete( $slug );
			}

			self::redirect_to_list( $allow ? 'allowed' : 'disallowed' );
		}

		$result = Repository::upsert( $slug, $override );
		self::redirect_to_list( $result ? ( $allow ? 'allowed' : 'disallowed' ) : 'error' );
	}

	/**
	 * Redirects to the plugin's list page with an admin notice.
	 *
	 * Used after delete and toggle operations that always return the user
	 * to the list view regardless of their previous location.
	 *
	 * @param string $notice Notice key to append as the `aam_notice` parameter.
	 * @return void
	 */
	private static function redirect_to_list( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'acrossai-abilities-manager',
					'aam_notice' => $notice,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Returns the save button target selected by the user.
	 *
	 * Reads `aam_save_target` from the POST body and validates it against the
	 * two allowed values ('stay' and 'exit'). Any unknown value falls back to
	 * 'stay' so the user remains on the edit screen.
	 *
	 * @return string Either `stay` (remain on edit screen) or `exit` (go to list).
	 */
	private static function save_target(): string {
		$target = isset( $_POST['aam_save_target'] ) ? sanitize_key( wp_unslash( $_POST['aam_save_target'] ) ) : 'stay'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is checked in save() before this is called.

		// Only allow the two known values; fall back to 'stay' for anything else.
		return 'exit' === $target ? 'exit' : 'stay';
	}

	/**
	 * Redirects after a save attempt based on the selected save target.
	 *
	 * When $save_target is 'stay', the redirect returns to the edit form for
	 * the same ability, appending an ID or slug parameter so the form can
	 * reload the freshly saved data. When $save_target is 'exit', the
	 * redirect goes to the list page.
	 *
	 * @param string $slug        Ability slug being edited.
	 * @param string $notice      Notice slug to display after redirect.
	 * @param string $save_target Selected save behavior ('stay' or 'exit').
	 * @return void
	 */
	private static function redirect_after_save( string $slug, string $notice, string $save_target ): void {
		$args = array(
			'page'       => 'acrossai-abilities-manager',
			'aam_notice' => $notice,
		);

		// 'stay' means: go back to the edit form, not the list view.
		if ( 'exit' !== $save_target ) {
			$args['action'] = 'edit';
			$override       = Repository::get_by_slug( $slug );
			// Prefer the database row ID in the URL so the page survives slug renames.
			if ( is_array( $override ) && ! empty( $override['id'] ) ) {
				$args['id'] = (int) $override['id'];
			} else {
				$args['slug'] = $slug;
			}
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Renders a tri-state <select> element for nullable boolean annotation fields.
	 *
	 * The three options are `null` (inherit default), `1` (true), and `0` (false).
	 * Uses printf with positional placeholders to safely inject the pre-escaped
	 * `selected` attributes.
	 *
	 * @param string    $name     HTML `name` attribute for the select element.
	 * @param bool|null $value    Currently selected value (true, false, or null).
	 * @param bool      $disabled Whether the select should be rendered as disabled.
	 * @return void
	 */
	private static function select( string $name, ?bool $value, bool $disabled ): void {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- disabled() and selected_option() return safe, static HTML attribute strings.
		printf(
			'<select name="%1$s" %2$s><option value="null"%3$s>null</option><option value="1"%4$s>true</option><option value="0"%5$s>false</option></select>',
			esc_attr( $name ),
			disabled( $disabled, true, false ),
			self::selected_option( null, $value ),
			self::selected_option( true, $value ),
			self::selected_option( false, $value )
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}


	/**
	 * Renders the MCP type <select> element.
	 *
	 * Restricts the value to the three known MCP endpoint types via
	 * sanitize_mcp_type() before checking the selected state.
	 *
	 * @param string $value    Currently selected MCP type string.
	 * @param bool   $disabled Whether the select should be rendered as disabled.
	 * @return void
	 */
	private static function mcp_type_select( string $value, bool $disabled ): void {
		$options = array(
			'tools'     => __( 'Tools', 'acrossai-abilities-manager' ),
			'resources' => __( 'Resources', 'acrossai-abilities-manager' ),
			'prompts'   => __( 'Prompts', 'acrossai-abilities-manager' ),
		);

		$value = self::sanitize_mcp_type( $value );

		echo '<select name="mcp_type" ' . disabled( $disabled, true, false ) . '>';
		foreach ( $options as $option_value => $label ) {
			echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}


	/**
	 * Extracts and normalizes all relevant field values from a POST submission.
	 *
	 * Checkbox fields (`site_allowed`, `show_in_rest`, `mcp_public`) are read
	 * as booleans via `isset()` since an unchecked checkbox sends no value.
	 * Tri-state select fields are normalized through normalize_nullable_bool().
	 *
	 * @return array<string, mixed> Normalized submitted values keyed by field name.
	 */
	private static function submitted_values(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is checked in save() before this helper is called.
		return array(
			'site_allowed' => isset( $_POST['site_allowed'] ),
			'readonly'     => self::normalize_nullable_bool( isset( $_POST['readonly'] ) ? sanitize_text_field( wp_unslash( $_POST['readonly'] ) ) : null ),
			'destructive'  => self::normalize_nullable_bool( isset( $_POST['destructive'] ) ? sanitize_text_field( wp_unslash( $_POST['destructive'] ) ) : null ),
			'idempotent'   => self::normalize_nullable_bool( isset( $_POST['idempotent'] ) ? sanitize_text_field( wp_unslash( $_POST['idempotent'] ) ) : null ),
			'show_in_rest' => isset( $_POST['show_in_rest'] ),
			'mcp_public'   => isset( $_POST['mcp_public'] ),
			'mcp_type'     => self::sanitize_mcp_type( isset( $_POST['mcp_type'] ) ? sanitize_text_field( wp_unslash( $_POST['mcp_type'] ) ) : '' ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}


	/**
	 * Merges a stored override row with live ability metadata to produce display values.
	 *
	 * When an override exists for a field, the override value is shown.
	 * Otherwise the live ability's registered value is used as the fallback,
	 * giving the form a meaningful pre-filled state even for abilities without
	 * any stored overrides.
	 *
	 * @param array<string, mixed>|null $override Stored override row, or null.
	 * @param \WP_Ability|mixed         $ability  Live WP_Ability object, or null.
	 * @return array<string, mixed> Resolved display values keyed by field name.
	 */
	private static function resolved_values( ?array $override, $ability ): array {
		$meta        = $ability instanceof \WP_Ability ? $ability->get_meta() : array();
		$annotations = is_array( $meta['annotations'] ?? null ) ? $meta['annotations'] : array();

		return array(
			'site_allowed' => self::coalesce( $override['site_allowed'] ?? null, true ),
			'readonly'     => self::coalesce( $override['readonly'] ?? null, $annotations['readonly'] ?? null ),
			'destructive'  => self::coalesce( $override['destructive'] ?? null, $annotations['destructive'] ?? null ),
			'idempotent'   => self::coalesce( $override['idempotent'] ?? null, $annotations['idempotent'] ?? null ),
			'show_in_rest' => self::coalesce( $override['show_in_rest'] ?? null, $meta['show_in_rest'] ?? false ),
			'mcp_public'   => self::coalesce( $override['mcp_public'] ?? null, $meta['mcp']['public'] ?? false ),
			'mcp_type'     => self::sanitize_mcp_type( self::coalesce( $override['mcp_type'] ?? '', $meta['mcp']['type'] ?? 'tools' ) ),
		);
	}

	/**
	 * Computes a diff-only override payload by comparing submitted values to live defaults.
	 *
	 * Only fields whose submitted value differs from the ability's registered
	 * default are included in the returned array. An empty array means the
	 * submission carries no meaningful changes — the caller should treat this
	 * as a no-op rather than writing a redundant row.
	 *
	 * When no live WP_Ability object is available, the submitted values are
	 * returned as-is since there is no default to compare against.
	 *
	 * @param array<string, mixed> $submitted Normalized values from the form submission.
	 * @param \WP_Ability|mixed    $ability   Live WP_Ability object, or null.
	 * @return array<string, mixed> Fields that deviate from the live ability defaults.
	 */
	private static function build_override_values( array $submitted, $ability ): array {
		// Without a live ability object there are no defaults to diff against.
		if ( ! $ability instanceof \WP_Ability ) {
			return $submitted;
		}

		$defaults  = self::default_values( $ability );
		$overrides = array();
		$fields    = array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'mcp_public', 'mcp_type' );

		foreach ( $fields as $field ) {
			// Include the field only when it diverges from the live default value.
			if ( $submitted[ $field ] !== $defaults[ $field ] ) {
				$overrides[ $field ] = $submitted[ $field ];
			}
		}

		return $overrides;
	}


	/**
	 * Ensures previously stored override keys are cleared when a field returns to
	 * its live default but the row still needs to exist for other overrides.
	 *
	 * Without this step, partial updates would preserve stale stored values for
	 * fields omitted from the current diff payload.
	 *
	 * @param array<string, mixed>      $override          Current diff-only override payload.
	 * @param array<string, mixed>|null $existing_override Previously stored override row, if any.
	 * @return array<string, mixed> Override payload safe to persist.
	 */
	private static function prepare_override_for_save( array $override, ?array $existing_override ): array {
		// Nothing to merge when there is no existing row, or when the diff is already empty.
		if ( ! is_array( $existing_override ) || array() === $override ) {
			return $override;
		}

		foreach ( array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'mcp_public', 'mcp_type' ) as $field ) {
			// Skip fields that are already included in the current diff payload.
			if ( array_key_exists( $field, $override ) ) {
				continue;
			}

			// Explicitly null out fields that were previously stored but are no longer
			// overridden, so the database row reflects the current state accurately.
			if ( self::has_stored_override_value( $existing_override, $field ) ) {
				$override[ $field ] = null;
			}
		}

		return $override;
	}

	/**
	 * Checks whether the stored row currently contains an explicit override value
	 * for the provided field.
	 *
	 * MCP type is treated differently: an empty string is not considered an
	 * override because the UI always sends a non-empty string for this field.
	 *
	 * @param array<string, mixed> $override Stored override row.
	 * @param string               $field    Override field name.
	 * @return bool True when the field currently stores an override.
	 */
	private static function has_stored_override_value( array $override, string $field ): bool {
		// The field was never stored — no override to clear.
		if ( ! array_key_exists( $field, $override ) ) {
			return false;
		}

		// For mcp_type, an empty string means "not set" rather than a real override.
		if ( 'mcp_type' === $field ) {
			return '' !== (string) $override[ $field ];
		}

		return null !== $override[ $field ];
	}

	/**
	 * Returns the live registered default values for a WP_Ability object.
	 *
	 * These defaults are used by build_override_values() to compute the diff.
	 * The result mirrors the structure returned by resolved_values() so that
	 * field-by-field comparisons are straightforward.
	 *
	 * @param \WP_Ability $ability Live ability object to extract defaults from.
	 * @return array<string, mixed> Default values keyed by field name.
	 */
	private static function default_values( \WP_Ability $ability ): array {
		$meta        = $ability->get_meta();
		$annotations = is_array( $meta['annotations'] ?? null ) ? $meta['annotations'] : array();

		return array(
			'site_allowed' => true,
			'readonly'     => self::normalize_nullable_bool( $annotations['readonly'] ?? null ),
			'destructive'  => self::normalize_nullable_bool( $annotations['destructive'] ?? null ),
			'idempotent'   => self::normalize_nullable_bool( $annotations['idempotent'] ?? null ),
			'show_in_rest' => (bool) ( $meta['show_in_rest'] ?? false ),
			'mcp_public'   => (bool) ( $meta['mcp']['public'] ?? false ),
			'mcp_type'     => self::default_mcp_type( $meta ),
		);
	}


	/**
	 * Returns the real stored/live default MCP type for diff comparisons.
	 *
	 * The edit form may display `tools` as the UI default, but when the live
	 * ability metadata has no `mcp.type` at all we must treat that as an empty
	 * default so selecting `tools` becomes a real override and gets persisted.
	 *
	 * @param array<string, mixed> $meta Ability metadata array from get_meta().
	 * @return string Default MCP type used for save diffing; empty string when not set.
	 */
	private static function default_mcp_type( array $meta ): string {
		// If the ability does not declare an mcp.type at all, treat the default as empty
		// so choosing 'tools' in the UI still produces a diff worth saving.
		if ( ! isset( $meta['mcp'] ) || ! is_array( $meta['mcp'] ) || ! array_key_exists( 'type', $meta['mcp'] ) ) {
			return '';
		}

		return self::sanitize_mcp_type( $meta['mcp']['type'] );
	}

	/**
	 * Validates and sanitizes an MCP type string.
	 *
	 * Only 'tools', 'resources', and 'prompts' are valid values. Any other
	 * input (including empty string) falls back to 'tools' so the database
	 * always stores a valid enum-like value.
	 *
	 * @param mixed $value Raw value to sanitize.
	 * @return string One of 'tools', 'resources', or 'prompts'.
	 */
	private static function sanitize_mcp_type( $value ): string {
		$value = sanitize_key( (string) $value );

		// Fall back to 'tools' for unrecognised values to keep the stored data valid.
		return in_array( $value, array( 'tools', 'resources', 'prompts' ), true ) ? $value : 'tools';
	}


	/**
	 * Converts a wide variety of truthy/falsy inputs to a nullable boolean.
	 *
	 * Used when normalizing values coming from the HTML form where the
	 * tri-state select sends the strings 'null', '1', and '0'.
	 * Returns null to represent "inherit the live default" state.
	 *
	 * @param mixed $value Value to normalize.
	 * @return bool|null Normalized boolean, or null when the input represents "not set".
	 */
	private static function normalize_nullable_bool( $value ): ?bool {
		// Treat null, empty string, and the literal 'null' as "no value stored".
		if ( null === $value || '' === $value || 'null' === $value ) {
			return null;
		}

		// PHP booleans are already in the right form.
		if ( is_bool( $value ) ) {
			return $value;
		}

		// Cast numeric types (0 → false, anything else → true).
		if ( is_numeric( $value ) ) {
			return (bool) (int) $value;
		}

		$value = strtolower( trim( (string) $value ) );

		// Accept common English affirmative strings.
		if ( in_array( $value, array( 'true', 'yes', 'on' ), true ) ) {
			return true;
		}

		// Accept common English negative strings.
		if ( in_array( $value, array( 'false', 'no', 'off' ), true ) ) {
			return false;
		}

		return null;
	}


	/**
	 * Returns the HTML `selected="selected"` attribute string when the two values match.
	 *
	 * Both parameters are typed as nullable booleans so that null/null, true/true,
	 * and false/false all produce the selected attribute correctly.
	 *
	 * @param bool|null $expected The value associated with this <option>.
	 * @param bool|null $actual   The currently selected value.
	 * @return string ' selected="selected"' when values match, otherwise empty string.
	 */
	private static function selected_option( ?bool $expected, ?bool $actual ): string {
		return $expected === $actual ? ' selected="selected"' : '';
	}

	/**
	 * Renders an HTML-safe category display value with an optional slug subtitle.
	 *
	 * When the category label and slug differ (case-insensitive), the slug is
	 * appended in a <small> element so editors can see both representations.
	 *
	 * @param string $category Human-readable category label.
	 * @param string $slug     Machine-readable category slug.
	 * @return string Safe HTML string for insertion via wp_kses_post().
	 */
	private static function render_category_value( string $category, string $slug ): string {
		// Return an em-dash placeholder when there is no category.
		if ( '' === $category ) {
			return '&mdash;';
		}

		$value = esc_html( $category );

		// Append the slug in small text when it differs from the human-readable label.
		if ( '' !== $slug && strtolower( $category ) !== strtolower( $slug ) ) {
			$value .= '<br /><small>' . esc_html( $slug ) . '</small>';
		}

		return $value;
	}

	/**
	 * Looks up the human-readable label for an ability category slug.
	 *
	 * Returns an empty string when the slug is empty or when the Abilities API
	 * is not available (e.g. on older WordPress versions). Falls back to the
	 * raw slug when the category object cannot be found in the registry.
	 *
	 * @param string $slug Category slug to look up.
	 * @return string Human-readable category label, raw slug, or empty string.
	 */
	private static function ability_category_label( string $slug ): string {
		// Without a slug or the API function there is nothing to look up.
		if ( '' === $slug || ! function_exists( 'wp_get_ability_categories' ) ) {
			return '';
		}

		$categories = wp_get_ability_categories();
		$category   = $categories[ $slug ] ?? null;

		// Return the label when a WP_Ability_Category object is found for this slug.
		if ( $category instanceof \WP_Ability_Category ) {
			return $category->get_label();
		}

		return $slug;
	}

	/**
	 * Returns the $override value when it is non-null and non-empty, otherwise $fallback.
	 *
	 * Used to merge stored override values with live ability metadata so that
	 * the form shows meaningful pre-filled values even for abilities without
	 * any stored overrides.
	 *
	 * @param mixed $override Stored override value (may be null or empty string).
	 * @param mixed $fallback Live ability default to use when override is absent.
	 * @return mixed The override if set, otherwise the fallback.
	 */
	private static function coalesce( $override, $fallback ) {
		// Treat both null and empty string as "no override stored".
		return ( null === $override || '' === $override ) ? $fallback : $override;
	}

	/**
	 * Infers the provider identifier from an ability's namespace segment.
	 *
	 * The namespace is the first path segment of the ability slug before the
	 * first `/`. Known WordPress core namespaces map to `core`. Active theme
	 * slugs map to `theme:<slug>`. Everything else is returned as-is and
	 * treated as a plugin provider.
	 *
	 * @param string $slug Ability slug to inspect.
	 * @return string Provider identifier such as 'core', 'theme:my-theme', or a plugin slug.
	 */
	private static function detect_provider( string $slug ): string {
		$namespace = sanitize_key( explode( '/', $slug )[0] ?? '' );

		// Map well-known WordPress core namespaces to a single canonical provider string.
		if ( in_array( $namespace, array( 'wordpress', 'wp', 'core' ), true ) ) {
			return 'core';
		}

		$stylesheet = sanitize_key( (string) get_stylesheet() );
		$template   = sanitize_key( (string) get_template() );

		// If the namespace matches the active theme (child or parent), tag it as a theme provider.
		if ( in_array( $namespace, array( $stylesheet, $template ), true ) ) {
			return 'theme:' . $namespace;
		}

		// Fall back to the raw namespace, or 'unknown' when the slug has no namespace segment.
		return '' !== $namespace ? $namespace : 'unknown';
	}
}
