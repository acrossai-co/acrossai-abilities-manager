<?php
/**
 * Add/Edit custom ability admin page.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Admin;

use AcrossAI_Abilities_Manager\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the add/edit custom ability page.
 *
 * Handles rendering the form for creating and editing custom abilities.
 * Supports both create mode (no query param) and edit mode (slug query param).
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Add_Ability_Page {

	/**
	 * Menu slug for the add ability page.
	 */
	private const MENU_SLUG = 'acrossai-add-ability';

	/**
	 * Nonce action for the add/edit form.
	 */
	private const NONCE_ACTION = 'acrossai_save_ability';

	/**
	 * Nonce field name.
	 */
	private const NONCE_NAME = 'acrossai_ability_nonce';

	/**
	 * Registers the admin page and hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_form_submission' ) );
	}

	/**
	 * Adds the submenu page under Tools.
	 *
	 * @return void
	 */
	public static function add_admin_menu(): void {
		add_submenu_page(
			'tools.php',
			esc_html__( 'Add New Ability', 'acrossai-abilities-manager' ),
			esc_html__( 'Add New Ability', 'acrossai-abilities-manager' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Handles form submission via REST API.
	 *
	 * @return void
	 */
	public static function handle_form_submission(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';

		if ( self::MENU_SLUG !== $page ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			add_settings_error(
				'acrossai_ability_error',
				'nonce_error',
				esc_html__( 'Security check failed.', 'acrossai-abilities-manager' ),
				'error'
			);
			return;
		}

		self::save_ability();
	}

	/**
	 * Saves a custom ability via REST API.
	 *
	 * @return void
	 */
	private static function save_ability(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$slug = isset( $_POST['ability_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['ability_slug'] ) ) : '';

		if ( empty( $slug ) ) {
			add_settings_error(
				'acrossai_ability_error',
				'empty_slug',
				esc_html__( 'Ability slug is required.', 'acrossai-abilities-manager' ),
				'error'
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		if ( empty( $label ) ) {
			add_settings_error(
				'acrossai_ability_error',
				'empty_label',
				esc_html__( 'Label is required.', 'acrossai-abilities-manager' ),
				'error'
			);
			return;
		}

		// Prepare data from form submission.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$data = array(
			'label'               => $label,
			'description'         => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'category'            => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'status'              => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'input_schema'        => isset( $_POST['input_schema'] ) ? sanitize_textarea_field( wp_unslash( $_POST['input_schema'] ) ) : '',
			'output_schema'       => isset( $_POST['output_schema'] ) ? sanitize_textarea_field( wp_unslash( $_POST['output_schema'] ) ) : '',
			'execute_callback'    => isset( $_POST['execute_callback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['execute_callback'] ) ) : '',
			'permission_callback' => isset( $_POST['permission_callback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['permission_callback'] ) ) : '',
			'version'             => isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '1.0',
			'mcp_type'            => isset( $_POST['mcp_type'] ) ? sanitize_text_field( wp_unslash( $_POST['mcp_type'] ) ) : '',
			'custom_meta'         => isset( $_POST['custom_meta'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_meta'] ) ) : '',
			'readonly'            => isset( $_POST['readonly'] ) ? true : false,
			'destructive'         => isset( $_POST['destructive'] ) ? true : false,
			'idempotent'          => isset( $_POST['idempotent'] ) ? true : false,
			'show_in_rest'        => isset( $_POST['show_in_rest'] ) ? true : false,
			'mcp_public'          => isset( $_POST['mcp_public'] ) ? true : false,
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Validate JSON fields if they're provided.
		if ( ! empty( $data['input_schema'] ) && ! self::is_valid_json( $data['input_schema'] ) ) {
			add_settings_error(
				'acrossai_ability_error',
				'invalid_input_schema',
				esc_html__( 'Input Schema must be valid JSON.', 'acrossai-abilities-manager' ),
				'error'
			);
			return;
		}

		if ( ! empty( $data['output_schema'] ) && ! self::is_valid_json( $data['output_schema'] ) ) {
			add_settings_error(
				'acrossai_ability_error',
				'invalid_output_schema',
				esc_html__( 'Output Schema must be valid JSON.', 'acrossai-abilities-manager' ),
				'error'
			);
			return;
		}

		if ( ! empty( $data['custom_meta'] ) && ! self::is_valid_json( $data['custom_meta'] ) ) {
			add_settings_error(
				'acrossai_ability_error',
				'invalid_custom_meta',
				esc_html__( 'Custom Meta must be valid JSON.', 'acrossai-abilities-manager' ),
				'error'
			);
			return;
		}

		// Use the Repository to save directly instead of REST API.
		$ability = Repository::upsert_custom_ability( $slug, $data );

		if ( null === $ability ) {
			add_settings_error(
				'acrossai_ability_error',
				'save_error',
				esc_html__( 'Failed to save custom ability.', 'acrossai-abilities-manager' ),
				'error'
			);
			return;
		}

		// Determine redirect based on submit button.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$save_and_exit = isset( $_POST['save_and_exit'] );

		add_settings_error(
			'acrossai_ability_success',
			'save_success',
			esc_html__( 'Ability saved successfully.', 'acrossai-abilities-manager' ),
			'success'
		);

		if ( $save_and_exit ) {
			wp_safe_redirect( admin_url( 'admin.php?page=acrossai-abilities-manager' ) );
			exit;
		}
	}

	/**
	 * Renders the add/edit ability page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage abilities.', 'acrossai-abilities-manager' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug    = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';
		$ability = ! empty( $slug ) ? Repository::get_custom_ability( $slug ) : null;
		$is_edit = ! empty( $slug ) && null !== $ability;
		$title   = $is_edit ? esc_html__( 'Edit Ability', 'acrossai-abilities-manager' ) : esc_html__( 'Add New Ability', 'acrossai-abilities-manager' );

		// Set form values from the ability data.
		$form_data = array(
			'ability_slug'        => $ability['ability_slug'] ?? '',
			'label'               => $ability['label'] ?? '',
			'description'         => $ability['description'] ?? '',
			'category'            => $ability['category'] ?? '',
			'status'              => $ability['status'] ?? 'active',
			'input_schema'        => $ability['input_schema'] ?? '',
			'output_schema'       => $ability['output_schema'] ?? '',
			'execute_callback'    => $ability['execute_callback'] ?? '',
			'permission_callback' => $ability['permission_callback'] ?? '',
			'version'             => $ability['version'] ?? '1.0',
			'mcp_type'            => $ability['mcp_type'] ?? '',
			'custom_meta'         => $ability['custom_meta'] ?? '',
			'readonly'            => $ability['readonly'] ?? false,
			'destructive'         => $ability['destructive'] ?? false,
			'idempotent'          => $ability['idempotent'] ?? false,
			'show_in_rest'        => $ability['show_in_rest'] ?? false,
			'mcp_public'          => $ability['mcp_public'] ?? false,
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>

			<?php settings_errors(); ?>

			<form method="post" class="acrossai-ability-form">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="ability_slug"><?php esc_html_e( 'Ability Slug', 'acrossai-abilities-manager' ); ?> <span class="description">(required)</span></label>
							</th>
							<td>
								<input
									type="text"
									id="ability_slug"
									name="ability_slug"
									class="regular-text"
									placeholder="my-site/custom-name"
									value="<?php echo esc_attr( $form_data['ability_slug'] ); ?>"
									<?php echo $is_edit ? 'readonly' : ''; ?>
									required
								/>
								<?php if ( ! $is_edit ) : ?>
									<p class="description"><?php esc_html_e( 'A unique identifier for this ability. Format: namespace/name. Cannot be changed after creation.', 'acrossai-abilities-manager' ); ?></p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'This field cannot be edited after creation.', 'acrossai-abilities-manager' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="label"><?php esc_html_e( 'Label', 'acrossai-abilities-manager' ); ?> <span class="description">(required)</span></label>
							</th>
							<td>
								<input
									type="text"
									id="label"
									name="label"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'Display Name', 'acrossai-abilities-manager' ); ?>"
									value="<?php echo esc_attr( $form_data['label'] ); ?>"
									required
								/>
								<p class="description"><?php esc_html_e( 'The human-readable display name for this ability.', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="description"><?php esc_html_e( 'Description', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<textarea
									id="description"
									name="description"
									rows="4"
									class="large-text"
									placeholder="<?php esc_attr_e( 'Describe what this ability does', 'acrossai-abilities-manager' ); ?>"
								><?php echo esc_textarea( $form_data['description'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'A description of what this ability does and how it should be used.', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="category"><?php esc_html_e( 'Category', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="category"
									name="category"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'general', 'acrossai-abilities-manager' ); ?>"
									value="<?php echo esc_attr( $form_data['category'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Categorize this ability for better organization.', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="status"><?php esc_html_e( 'Status', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<select id="status" name="status" class="regular-text">
									<option value="active" <?php selected( $form_data['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'acrossai-abilities-manager' ); ?></option>
									<option value="draft" <?php selected( $form_data['status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'acrossai-abilities-manager' ); ?></option>
									<option value="archived" <?php selected( $form_data['status'], 'archived' ); ?>><?php esc_html_e( 'Archived', 'acrossai-abilities-manager' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Controls the availability of this ability.', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="version"><?php esc_html_e( 'Version', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="version"
									name="version"
									class="regular-text"
									placeholder="1.0"
									value="<?php echo esc_attr( $form_data['version'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'The version of this ability (e.g., 1.0, 2.1).', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="input_schema"><?php esc_html_e( 'Input Schema', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<textarea
									id="input_schema"
									name="input_schema"
									rows="10"
									class="large-text code"
									placeholder="<?php esc_attr_e( '{ "type": "object", "properties": { ... } }', 'acrossai-abilities-manager' ); ?>"
								><?php echo esc_textarea( $form_data['input_schema'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'JSON Schema describing the input parameters for this ability.', 'acrossai-abilities-manager' ); ?></p>
								<button type="button" class="button" onclick="window.acrossaiFormatJSON('input_schema')"><?php esc_html_e( 'Format JSON', 'acrossai-abilities-manager' ); ?></button>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="output_schema"><?php esc_html_e( 'Output Schema', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<textarea
									id="output_schema"
									name="output_schema"
									rows="10"
									class="large-text code"
									placeholder="<?php esc_attr_e( '{ "type": "object", "properties": { ... } }', 'acrossai-abilities-manager' ); ?>"
								><?php echo esc_textarea( $form_data['output_schema'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'JSON Schema describing the output of this ability.', 'acrossai-abilities-manager' ); ?></p>
								<button type="button" class="button" onclick="window.acrossaiFormatJSON('output_schema')"><?php esc_html_e( 'Format JSON', 'acrossai-abilities-manager' ); ?></button>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="execute_callback"><?php esc_html_e( 'Execute Callback', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<textarea
									id="execute_callback"
									name="execute_callback"
									rows="4"
									class="large-text code"
									placeholder="<?php esc_attr_e( 'function_name or Class::method', 'acrossai-abilities-manager' ); ?>"
								><?php echo esc_textarea( $form_data['execute_callback'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'The function or method to call when executing this ability.', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="permission_callback"><?php esc_html_e( 'Permission Callback', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<textarea
									id="permission_callback"
									name="permission_callback"
									rows="4"
									class="large-text code"
									placeholder="<?php esc_attr_e( 'function_name or Class::method', 'acrossai-abilities-manager' ); ?>"
								><?php echo esc_textarea( $form_data['permission_callback'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'The function or method to call to check if the user can execute this ability.', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Metadata Flags', 'acrossai-abilities-manager' ); ?>
							</th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="readonly" value="1" <?php checked( $form_data['readonly'] ); ?> />
										<?php esc_html_e( 'Readonly', 'acrossai-abilities-manager' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" name="destructive" value="1" <?php checked( $form_data['destructive'] ); ?> />
										<?php esc_html_e( 'Destructive', 'acrossai-abilities-manager' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" name="idempotent" value="1" <?php checked( $form_data['idempotent'] ); ?> />
										<?php esc_html_e( 'Idempotent', 'acrossai-abilities-manager' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" name="show_in_rest" value="1" <?php checked( $form_data['show_in_rest'] ); ?> />
										<?php esc_html_e( 'Show in REST', 'acrossai-abilities-manager' ); ?>
									</label>
									<br />
									<label>
										<input type="checkbox" name="mcp_public" value="1" <?php checked( $form_data['mcp_public'] ); ?> />
										<?php esc_html_e( 'MCP Public', 'acrossai-abilities-manager' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Select the attributes that apply to this ability.', 'acrossai-abilities-manager' ); ?></p>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="mcp_type"><?php esc_html_e( 'MCP Type', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<select id="mcp_type" name="mcp_type" class="regular-text">
									<option value=""><?php esc_html_e( '— None —', 'acrossai-abilities-manager' ); ?></option>
									<option value="tools" <?php selected( $form_data['mcp_type'], 'tools' ); ?>><?php esc_html_e( 'Tools', 'acrossai-abilities-manager' ); ?></option>
									<option value="resources" <?php selected( $form_data['mcp_type'], 'resources' ); ?>><?php esc_html_e( 'Resources', 'acrossai-abilities-manager' ); ?></option>
									<option value="prompts" <?php selected( $form_data['mcp_type'], 'prompts' ); ?>><?php esc_html_e( 'Prompts', 'acrossai-abilities-manager' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'The Model Context Protocol type for this ability.', 'acrossai-abilities-manager' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="custom_meta"><?php esc_html_e( 'Custom Meta', 'acrossai-abilities-manager' ); ?></label>
							</th>
							<td>
								<textarea
									id="custom_meta"
									name="custom_meta"
									rows="10"
									class="large-text code"
									placeholder="<?php esc_attr_e( '{ "key": "value" }', 'acrossai-abilities-manager' ); ?>"
								><?php echo esc_textarea( $form_data['custom_meta'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Additional JSON metadata for this ability.', 'acrossai-abilities-manager' ); ?></p>
								<button type="button" class="button" onclick="window.acrossaiFormatJSON('custom_meta')"><?php esc_html_e( 'Format JSON', 'acrossai-abilities-manager' ); ?></button>
							</td>
						</tr>
					</tbody>
				</table>

				<div class="acrossai-ability-actions">
					<?php submit_button( esc_html__( 'Save Ability', 'acrossai-abilities-manager' ), 'primary', 'save', false ); ?>
					<?php submit_button( esc_html__( 'Save and Exit', 'acrossai-abilities-manager' ), 'secondary', 'save_and_exit', false ); ?>
					<?php if ( $is_edit ) : ?>
						<?php submit_button( esc_html__( 'Reset to Live', 'acrossai-abilities-manager' ), 'delete', 'reset_override', false ); ?>
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=acrossai-abilities-manager' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'acrossai-abilities-manager' ); ?></a>
				</div>
			</form>
		</div>

		<script>
			window.acrossaiFormatJSON = function( fieldId ) {
				const field = document.getElementById( fieldId );
				if ( ! field ) return;

				try {
					const json = JSON.parse( field.value );
					field.value = JSON.stringify( json, null, 2 );
					alert( '<?php esc_attr_e( 'JSON formatted successfully.', 'acrossai-abilities-manager' ); ?>' );
				} catch ( e ) {
					alert( '<?php esc_attr_e( 'Invalid JSON:', 'acrossai-abilities-manager' ); ?> ' + e.message );
				}
			};
		</script>

		<style>
			.acrossai-ability-form {
				max-width: 1000px;
			}

			.acrossai-ability-form .form-table {
				background: white;
				border: 1px solid #ddd;
				border-collapse: collapse;
			}

			.acrossai-ability-form .form-table th {
				background-color: #f5f5f5;
				font-weight: 600;
				padding: 15px;
				text-align: left;
				vertical-align: top;
				width: 200px;
			}

			.acrossai-ability-form .form-table td {
				padding: 15px;
				vertical-align: top;
			}

			.acrossai-ability-form .form-table tr:nth-child( even ) {
				background-color: #fafafa;
			}

			.acrossai-ability-form .description {
				display: block;
				margin-top: 5px;
				color: #666;
				font-size: 13px;
			}

			.acrossai-ability-form .regular-text,
			.acrossai-ability-form .large-text,
			.acrossai-ability-form select {
				max-width: 100%;
				width: 100%;
			}

			.acrossai-ability-form textarea.code {
				font-family: 'Courier New', Courier, monospace;
				font-size: 13px;
			}

			.acrossai-ability-form .button {
				margin-right: 5px;
				margin-top: 8px;
			}

			.acrossai-ability-actions {
				margin: 20px 0;
				padding: 20px;
				background: #f9f9f9;
				border: 1px solid #ddd;
				border-radius: 3px;
			}

			.acrossai-ability-actions .button {
				margin-right: 10px;
			}
		</style>
		<?php
	}

	/**
	 * Checks if a string is valid JSON.
	 *
	 * @param string $json_string The string to validate.
	 * @return bool True if valid JSON, false otherwise.
	 */
	private static function is_valid_json( string $json_string ): bool {
		if ( empty( $json_string ) ) {
			return true;
		}

		json_decode( $json_string );
		return JSON_ERROR_NONE === json_last_error();
	}
}
