<?php
/**
 * Abilities list table.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Admin;

use AcrossAI_Abilities_Manager\Database\Repository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom WP_List_Table for browsing all registered abilities, their overrides, and custom abilities.
 *
 * Merges live WP_Ability objects (from wp_get_abilities()) with stored
 * override rows and custom user-defined abilities from the database.
 * Abilities that appear in any source are shown in a unified view,
 * with a Type column distinguishing between provider abilities and custom abilities.
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 * @extends \WP_List_Table
 */
class List_Table extends \WP_List_Table {

	/**
	 * All currently registered WP_Ability objects keyed by slug.
	 *
	 * @var array<string, \WP_Ability>
	 */
	private array $abilities = array();

	/**
	 * Stored override rows from the database, keyed by ability slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $overrides = array();

	/**
	 * Custom user-defined abilities from the database, keyed by ability slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $custom_abilities = array();

	/**
	 * All registered WP_Ability_Category objects keyed by slug.
	 *
	 * @var array<string, \WP_Ability_Category>
	 */
	private array $categories = array();

	/**
	 * Running counts of abilities grouped by their provider kind.
	 *
	 * Populated during build_items() and used by render_stats_bar()
	 * to show per-tab totals in the provider filter bar.
	 *
	 * @var array<string, int>
	 */
	private array $provider_counts = array(
		'core'   => 0,
		'plugin' => 0,
		'theme'  => 0,
		'custom' => 0,
	);

	/**
	 * Constructs the list table and passes configuration to the parent class.
	 *
	 * The `screen` argument pins the table to the plugin's admin page so
	 * WordPress resolves column visibility and per-page settings correctly.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ability-override',
				'plural'   => 'ability-overrides',
				'ajax'     => false,
				'screen'   => 'tools_page_acrossai-abilities-manager',
			)
		);
	}

	/**
	 * Returns all column definitions for the list table.
	 *
	 * Used by WordPress to populate the Screen Options column-toggle list
	 * and to map column IDs to their rendered output via column_{id}() methods.
	 *
	 * @return array<string, string> Column ID => translated label pairs.
	 */
	public function get_columns(): array {
		return array(
			'name'         => __( 'Name', 'acrossai-abilities-manager' ),
			'slug'         => __( 'Slug', 'acrossai-abilities-manager' ),
			'type'         => __( 'Type', 'acrossai-abilities-manager' ),
			'label'        => __( 'Label', 'acrossai-abilities-manager' ),
			'provider'     => __( 'Provider', 'acrossai-abilities-manager' ),
			'category'     => __( 'Category', 'acrossai-abilities-manager' ),
			'status'       => __( 'Status', 'acrossai-abilities-manager' ),
			'site_allowed' => __( 'Allowed', 'acrossai-abilities-manager' ),
			'readonly'     => __( 'Readonly', 'acrossai-abilities-manager' ),
			'destructive'  => __( 'Destructive', 'acrossai-abilities-manager' ),
			'idempotent'   => __( 'Idempotent', 'acrossai-abilities-manager' ),
			'show_in_rest' => __( 'Show in REST', 'acrossai-abilities-manager' ),
			'mcp_public'   => __( 'MCP Public', 'acrossai-abilities-manager' ),
			'actions'      => __( 'Actions', 'acrossai-abilities-manager' ),
		);
	}

	/**
	 * Returns the sortable column definitions.
	 *
	 * Each entry maps a column ID to an array of [orderby_key, is_initially_sorted_desc].
	 * Sorting is handled in PHP by sort_items() since the full item list is loaded
	 * in memory rather than paginated at the database layer.
	 *
	 * @return array<string, array<int, mixed>> Sortable column configuration.
	 */
	public function get_sortable_columns(): array {
		return array(
			'name'         => array( 'name', false ),
			'slug'         => array( 'slug', false ),
			'type'         => array( 'type', false ),
			'label'        => array( 'label', false ),
			'provider'     => array( 'provider', false ),
			'category'     => array( 'category', false ),
			'status'       => array( 'status', false ),
			'site_allowed' => array( 'site_allowed', false ),
			'readonly'     => array( 'readonly', false ),
			'destructive'  => array( 'destructive', false ),
			'idempotent'   => array( 'idempotent', false ),
			'show_in_rest' => array( 'show_in_rest', false ),
			'mcp_public'   => array( 'mcp_public', false ),
		);
	}

	/**
	 * Loads data, sorts, filters, and paginates the item list.
	 *
	 * Pulls all registered abilities, all stored overrides, and all custom abilities,
	 * merges them into a unified item array, applies search/provider filters, sorts
	 * in PHP (because the merged list lives in memory), and finally slices the
	 * result to the current page.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->abilities        = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();
		$this->categories       = function_exists( 'wp_get_ability_categories' ) ? wp_get_ability_categories() : array();
		$override_result        = Repository::get_all( array( 'per_page' => 0 ) );
		$this->overrides        = array();
		$custom_result          = Repository::get_all_custom_abilities( array( 'per_page' => 0 ) );
		$this->custom_abilities = array();

		foreach ( $override_result['items'] as $override ) {
			$this->overrides[ $override['ability_slug'] ] = $override;
		}

		foreach ( $custom_result['items'] as $custom ) {
			$this->custom_abilities[ $custom['ability_slug'] ] = $custom;
		}

		$items  = $this->filter_items( $this->build_items() );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Fall back to a hard-coded default when get_current_screen() is unavailable.
		$hidden = $screen ? get_hidden_columns( $screen ) : array( 'destructive', 'idempotent', 'label', 'status' );

		usort( $items, array( $this, 'sort_items' ) );
		$this->_column_headers = array( $this->get_columns(), $hidden, $this->get_sortable_columns() );

		$per_page = $this->get_items_per_page( 'acrossai_abilities_manager_per_page', 20 );
		$page     = $this->get_pagenum();
		$total    = count( $items );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->items = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );
	}

	/**
	 * Renders the cell content for any column that does not have a dedicated method.
	 *
	 * Falls back to displaying the raw (escaped) string value of the field, or an
	 * em-dash when the field is absent from the item array.
	 *
	 * @param array<string, mixed> $item        Current row's data array.
	 * @param string               $column_name Column ID being rendered.
	 * @return string Escaped HTML for the cell.
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '&mdash;';
	}

	/**
	 * Renders the Name column with an edit link, description, and status indicators.
	 *
	 * Shows a locked-padlock indicator when the ability is disallowed on the site,
	 * and a saved-checkmark indicator when a stored override exists for the ability.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the Name cell.
	 */
	public function column_name( $item ): string {
		// Edit link differs based on whether this is a custom ability or an override.
		if ( 'custom' === $item['type'] ) {
			$edit_args = array(
				'page' => 'acrossai-add-ability',
				'slug' => $item['slug'],
			);
			$url       = add_query_arg( $edit_args, admin_url( 'admin.php' ) );
		} else {
			$edit_args = array(
				'page'   => 'acrossai-abilities-manager',
				'action' => 'edit',
			);

			// Prefer the database row ID in the URL so the link survives slug renames.
			if ( $item['id'] > 0 ) {
				$edit_args['id'] = $item['id'];
			} else {
				$edit_args['slug'] = $item['slug'];
			}

			$url = add_query_arg( $edit_args, admin_url( 'tools.php' ) );
		}

		$text = '<strong><a href="' . esc_url( $url ) . '">' . esc_html( $item['name'] ) . '</a></strong>';

		// Show the ability description as a small paragraph when available.
		if ( ! empty( $item['description'] ) ) {
			$text .= '<p class="description">' . esc_html( $item['description'] ) . '</p>';
		}

		// Show a checkmark badge when this ability has a stored override row.
		if ( ! empty( $item['has_override'] ) ) {
			$text .= '<p><span class="dashicons dashicons-saved" aria-hidden="true"></span> ' . esc_html__( 'Override saved', 'acrossai-abilities-manager' ) . '</p>';
		}

		// Show a lock badge when this ability has been explicitly disallowed on the site.
		if ( false === $item['site_allowed'] ) {
			$text .= '<p><span class="dashicons dashicons-lock" aria-hidden="true"></span> ' . esc_html__( 'Disallowed on this site', 'acrossai-abilities-manager' ) . '</p>';
		}

		return $text;
	}

	/**
	 * Renders the Slug column as a <code> element.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the Slug cell.
	 */
	public function column_slug( $item ): string {
		return '<code>' . esc_html( $item['slug'] ) . '</code>';
	}

	/**
	 * Renders the Type column to distinguish override from custom abilities.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string Translated 'Override' or 'Custom'.
	 */
	public function column_type( $item ): string {
		if ( 'custom' === $item['type'] ) {
			return esc_html__( 'Custom', 'acrossai-abilities-manager' );
		}
		return esc_html__( 'Override', 'acrossai-abilities-manager' );
	}

	/**
	 * Renders the Label column showing the ability label (for custom abilities).
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the Label cell.
	 */
	public function column_label( $item ): string {
		if ( 'custom' !== $item['type'] ) {
			return '&mdash;';
		}
		return esc_html( $item['label'] ?? '' );
	}

	/**
	 * Renders the Status column (for custom abilities).
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the Status cell.
	 */
	public function column_status( $item ): string {
		if ( 'custom' !== $item['type'] ) {
			return '&mdash;';
		}
		return esc_html( $item['status'] ?? 'active' );
	}

	/**
	 * Renders the Provider column with a human-readable provider label.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string Escaped plain-text provider label.
	 */
	public function column_provider( $item ): string {
		return esc_html( $this->provider_label( $item['provider'] ) );
	}

	/**
	 * Renders the Category column with an optional slug subtitle.
	 *
	 * Returns an em-dash when the ability has no category. When the label and
	 * slug differ (case-insensitive), the slug is shown in small text beneath.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the Category cell.
	 */
	public function column_category( $item ): string {
		// Show an em-dash placeholder when the ability has no category.
		if ( '' === $item['category'] ) {
			return '&mdash;';
		}

		$value = esc_html( $item['category'] );

		// When the label and slug differ, append the slug in smaller text for clarity.
		if ( '' !== $item['category_slug'] && strtolower( $item['category'] ) !== strtolower( $item['category_slug'] ) ) {
			$value .= '<br /><small>' . esc_html( $item['category_slug'] ) . '</small>';
		}

		return $value;
	}

	/**
	 * Renders the Allowed column as a Yes/No/dash string.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string Translated 'Yes', 'No', or '&mdash;'.
	 */
	public function column_site_allowed( $item ): string {
		return $this->render_bool_value( $item['site_allowed'] );
	}

	/**
	 * Renders the Readonly column as a Yes/No/dash string.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string Translated 'Yes', 'No', or '&mdash;'.
	 */
	public function column_readonly( $item ): string {
		return $this->render_bool_value( $item['readonly'] );
	}

	/**
	 * Renders the Destructive column as a Yes/No/dash string.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string Translated 'Yes', 'No', or '&mdash;'.
	 */
	public function column_destructive( $item ): string {
		return $this->render_bool_value( $item['destructive'] );
	}

	/**
	 * Renders the Idempotent column as a Yes/No/dash string.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string Translated 'Yes', 'No', or '&mdash;'.
	 */
	public function column_idempotent( $item ): string {
		return $this->render_bool_value( $item['idempotent'] );
	}

	/**
	 * Renders the Show in REST column as a Yes/No/dash string.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string Translated 'Yes', 'No', or '&mdash;'.
	 */
	public function column_show_in_rest( $item ): string {
		return $this->render_bool_value( $item['show_in_rest'] );
	}

	/**
	 * Renders the MCP Public column with an optional MCP type subtitle.
	 *
	 * When the ability is publicly visible to MCP clients and has a non-empty
	 * MCP type, the type string is appended in small text beneath the Yes/No value.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the MCP Public cell.
	 */
	public function column_mcp_public( $item ): string {
		$value = $this->render_bool_value( $item['mcp_public'] );

		// Append the MCP type only when the ability is public and has a type set.
		if ( true === $item['mcp_public'] && '' !== $item['mcp_type'] ) {
			$value .= '<br /><small>' . esc_html( $item['mcp_type'] ) . '</small>';
		}

		return $value;
	}

	/**
	 * Renders the Actions column with context-specific buttons.
	 *
	 * For override abilities:
	 *   - Edit: Opens the override edit form
	 *   - Allow/Disallow: Toggles site_allowed
	 *   - Reset: Deletes the override row (shown only when an override exists)
	 *
	 * For custom abilities:
	 *   - Edit: Opens the custom ability edit form
	 *   - Duplicate: Creates a copy with a new slug
	 *   - Delete: Removes the custom ability
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the Actions cell.
	 */
	public function column_actions( $item ): string {
		// Custom abilities have a different set of actions than provider overrides.
		if ( 'custom' === $item['type'] ) {
			return $this->column_actions_custom( $item );
		}

		// Provider override actions.
		$edit_args = array(
			'page'   => 'acrossai-abilities-manager',
			'action' => 'edit',
		);

		// Prefer the database row ID in the edit URL so it survives slug renames.
		if ( $item['id'] > 0 ) {
			$edit_args['id'] = $item['id'];
		} else {
			$edit_args['slug'] = $item['slug'];
		}

		$edit        = add_query_arg( $edit_args, admin_url( 'tools.php' ) );
		$toggle_url  = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => 'acrossai-abilities-manager',
					'aam_action'   => 'toggle_allowed',
					'slug'         => $item['slug'],
					// Send the opposite of the current state so the toggle flips the value.
					'site_allowed' => $item['site_allowed'] ? '0' : '1',
				),
				admin_url( 'tools.php' )
			),
			'aam_toggle_allowed_' . $item['slug'],
			'aam_toggle_allowed_nonce'
		);
		$toggle_text = $item['site_allowed'] ? __( 'Disallow', 'acrossai-abilities-manager' ) : __( 'Allow', 'acrossai-abilities-manager' );

		// Require a confirmation dialog only for the potentially disruptive Disallow action.
		$toggle_confirm = $item['site_allowed']
			? ' onclick="return window.confirm(' . esc_js( wp_json_encode( __( 'Disallow this ability on the site?', 'acrossai-abilities-manager' ) ) ) . ');"'
			: '';

		$actions = array(
			'<a class="button button-small button-primary" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'acrossai-abilities-manager' ) . '</a>',
			'<a class="button button-small" href="' . esc_url( $toggle_url ) . '"' . $toggle_confirm . '>' . esc_html( $toggle_text ) . '</a>',
		);

		// Show the Reset button only when a stored override row exists to remove.
		if ( ! empty( $item['has_override'] ) ) {
			$delete    = wp_nonce_url(
				add_query_arg(
					array(
						'page'       => 'acrossai-abilities-manager',
						'aam_action' => 'delete',
						'slug'       => $item['slug'],
					),
					admin_url( 'tools.php' )
				),
				'aam_delete_meta_' . $item['slug'],
				'aam_delete_nonce'
			);
			$actions[] = '<a class="button button-small" href="' . esc_url( $delete ) . '" onclick="return window.confirm(' . esc_js( wp_json_encode( __( 'Reset this override?', 'acrossai-abilities-manager' ) ) ) . ');">' . esc_html__( 'Reset', 'acrossai-abilities-manager' ) . '</a>';
		}

		return implode( ' ', $actions );
	}

	/**
	 * Renders the Actions column for custom abilities.
	 *
	 * Shows Edit, Duplicate, and Delete actions.
	 *
	 * @param array<string, mixed> $item Current row's data array.
	 * @return string HTML for the Actions cell.
	 */
	private function column_actions_custom( $item ): string {
		// Edit link points to the custom ability edit page.
		$edit_url = add_query_arg(
			array(
				'page' => 'acrossai-add-ability',
				'slug' => $item['slug'],
			),
			admin_url( 'admin.php' )
		);

		// Duplicate action triggers a copy of the custom ability.
		$duplicate_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'acrossai-abilities-manager',
					'aam_action' => 'duplicate_custom',
					'slug'       => $item['slug'],
				),
				admin_url( 'tools.php' )
			),
			'aam_duplicate_custom_' . $item['slug'],
			'aam_duplicate_custom_nonce'
		);

		// Delete action removes the custom ability.
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'acrossai-abilities-manager',
					'aam_action' => 'delete_custom',
					'slug'       => $item['slug'],
				),
				admin_url( 'tools.php' )
			),
			'aam_delete_custom_' . $item['slug'],
			'aam_delete_custom_nonce'
		);

		$actions = array(
			'<a class="button button-small button-primary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'acrossai-abilities-manager' ) . '</a>',
			'<a class="button button-small" href="' . esc_url( $duplicate_url ) . '">' . esc_html__( 'Duplicate', 'acrossai-abilities-manager' ) . '</a>',
			'<a class="button button-small" href="' . esc_url( $delete_url ) . '" onclick="return window.confirm(' . esc_js( wp_json_encode( __( 'Delete this custom ability?', 'acrossai-abilities-manager' ) ) ) . ');">' . esc_html__( 'Delete', 'acrossai-abilities-manager' ) . '</a>',
		);

		return implode( ' ', $actions );
	}

	/**
	 * Renders additional navigation controls above the table (Sort By dropdowns).
	 *
	 * WordPress calls this method for both 'top' and 'bottom' positions.
	 * The sort controls are only relevant before the table, so the method
	 * returns early for the bottom position to avoid duplicate markup.
	 *
	 * @param string $which Table navigation position: 'top' or 'bottom'.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		// Render the sort controls only at the top of the table.
		if ( 'top' !== $which ) {
			return;
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="acrossai-abilities-manager-orderby"><?php esc_html_e( 'Sort by', 'acrossai-abilities-manager' ); ?></label>
			<select name="orderby" id="acrossai-abilities-manager-orderby">
				<option value="name" <?php selected( $orderby, 'name' ); ?>><?php esc_html_e( 'Name', 'acrossai-abilities-manager' ); ?></option>
				<option value="slug" <?php selected( $orderby, 'slug' ); ?>><?php esc_html_e( 'Slug', 'acrossai-abilities-manager' ); ?></option>
				<option value="type" <?php selected( $orderby, 'type' ); ?>><?php esc_html_e( 'Type', 'acrossai-abilities-manager' ); ?></option>
				<option value="label" <?php selected( $orderby, 'label' ); ?>><?php esc_html_e( 'Label', 'acrossai-abilities-manager' ); ?></option>
				<option value="provider" <?php selected( $orderby, 'provider' ); ?>><?php esc_html_e( 'Provider', 'acrossai-abilities-manager' ); ?></option>
				<option value="category" <?php selected( $orderby, 'category' ); ?>><?php esc_html_e( 'Category', 'acrossai-abilities-manager' ); ?></option>
				<option value="status" <?php selected( $orderby, 'status' ); ?>><?php esc_html_e( 'Status', 'acrossai-abilities-manager' ); ?></option>
				<option value="site_allowed" <?php selected( $orderby, 'site_allowed' ); ?>><?php esc_html_e( 'Allowed', 'acrossai-abilities-manager' ); ?></option>
				<option value="readonly" <?php selected( $orderby, 'readonly' ); ?>><?php esc_html_e( 'Readonly', 'acrossai-abilities-manager' ); ?></option>
				<option value="destructive" <?php selected( $orderby, 'destructive' ); ?>><?php esc_html_e( 'Destructive', 'acrossai-abilities-manager' ); ?></option>
				<option value="idempotent" <?php selected( $orderby, 'idempotent' ); ?>><?php esc_html_e( 'Idempotent', 'acrossai-abilities-manager' ); ?></option>
				<option value="show_in_rest" <?php selected( $orderby, 'show_in_rest' ); ?>><?php esc_html_e( 'Show in REST', 'acrossai-abilities-manager' ); ?></option>
				<option value="mcp_public" <?php selected( $orderby, 'mcp_public' ); ?>><?php esc_html_e( 'MCP Public', 'acrossai-abilities-manager' ); ?></option>
			</select>
			<label class="screen-reader-text" for="acrossai-abilities-manager-order"><?php esc_html_e( 'Order', 'acrossai-abilities-manager' ); ?></label>
			<select name="order" id="acrossai-abilities-manager-order">
				<option value="asc" <?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'Ascending', 'acrossai-abilities-manager' ); ?></option>
				<option value="desc" <?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'Descending', 'acrossai-abilities-manager' ); ?></option>
			</select>
			<?php submit_button( __( 'Sort', 'acrossai-abilities-manager' ), 'secondary', 'acrossai_abilities_manager_sort', false ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the provider filter tab bar above the list table.
	 *
	 * Outputs an unordered list styled as WordPress's `subsubsub` navigation,
	 * with tabs for provider kinds (All, Core, Plugins, Themes, Custom) and count
	 * badges. The currently active tab is rendered as a <span> instead of an <a>.
	 *
	 * Must be called after prepare_items() so that provider_counts is populated.
	 *
	 * @return void
	 */
	public function render_stats_bar(): void {
		$current = isset( $_REQUEST['provider'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['provider'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs    = array(
			'all'    => array(
				'label' => __( 'All', 'acrossai-abilities-manager' ),
				'count' => array_sum( $this->provider_counts ),
			),
			'core'   => array(
				'label' => __( 'Core', 'acrossai-abilities-manager' ),
				'count' => $this->provider_counts['core'],
			),
			'plugin' => array(
				'label' => __( 'Plugins', 'acrossai-abilities-manager' ),
				'count' => $this->provider_counts['plugin'],
			),
			'theme'  => array(
				'label' => __( 'Themes', 'acrossai-abilities-manager' ),
				'count' => $this->provider_counts['theme'],
			),
			'custom' => array(
				'label' => __( 'Custom', 'acrossai-abilities-manager' ),
				'count' => $this->provider_counts['custom'],
			),
		);

		echo '<ul class="subsubsub">';

		$index = 0;
		foreach ( $tabs as $provider => $tab ) {
			$label = esc_html( $tab['label'] ) . ' <span class="count">(' . (int) $tab['count'] . ')</span>';

			echo '<li class="' . esc_attr( $provider ) . '">';

			// The active tab is a non-linked span; other tabs link to their filtered view.
			if ( $provider === $current ) {
				echo '<span class="current">' . wp_kses_post( $label ) . '</span>';
			} else {
				echo '<a href="' . esc_url( $this->provider_tab_url( $provider ) ) . '">' . wp_kses_post( $label ) . '</a>';
			}

			echo '</li>';

			if ( $index < count( $tabs ) - 1 ) {
				echo ' | ';
			}

			++$index;
		}

		echo '</ul>';
	}

	/**
	 * Builds the full merged item list from live abilities, stored overrides, and custom abilities.
	 *
	 * First iterates registered abilities; then iterates stored overrides to
	 * include any slug that has an override but is no longer registered; finally
	 * iterates custom abilities to include user-defined abilities.
	 *
	 * @return array<int, array<string, mixed>> Flat list of item arrays.
	 */
	private function build_items(): array {
		$items = array();

		foreach ( $this->abilities as $slug => $ability ) {
			$items[] = $this->build_item( (string) $slug, $ability, $this->overrides[ $slug ] ?? null );
		}

		foreach ( $this->overrides as $slug => $override ) {
			// Skip abilities that were already added in the first loop above.
			if ( isset( $this->abilities[ $slug ] ) ) {
				continue;
			}
			// Add override-only rows for abilities that are no longer registered.
			$items[] = $this->build_item( (string) $slug, null, $override );
		}

		foreach ( $this->custom_abilities as $slug => $custom ) {
			// Add custom ability rows.
			$items[] = $this->build_custom_item( (string) $slug, $custom );
		}

		return $items;
	}

	/**
	 * Builds the data array for a single provider ability row.
	 *
	 * Merges the live WP_Ability metadata with any stored override, resolves
	 * the display-ready values, detects the provider, looks up the category
	 * label, and increments the per-kind provider counter for the stats bar.
	 *
	 * @param string                    $slug     Ability slug.
	 * @param \WP_Ability|mixed         $ability  Live ability object, or null if not registered.
	 * @param array<string, mixed>|null $override Stored override row, or null.
	 * @return array<string, mixed> Flat item array ready for column_*() methods.
	 */
	private function build_item( string $slug, $ability, ?array $override ): array {
		$meta          = $ability instanceof \WP_Ability ? $ability->get_meta() : array();
		$annotations   = is_array( $meta['annotations'] ?? null ) ? $meta['annotations'] : array();
		$provider      = is_array( $override ) && ! empty( $override['provider'] ) ? (string) $override['provider'] : $this->detect_provider( $slug );
		$category_slug = $ability instanceof \WP_Ability ? $ability->get_category() : '';
		$category      = $this->category_label( $category_slug );
		$kind          = $this->provider_kind( $provider );

		// Increment the tab counter only for the three recognised provider kinds.
		if ( isset( $this->provider_counts[ $kind ] ) ) {
			++$this->provider_counts[ $kind ];
		}

		return array(
			'id'            => is_array( $override ) && ! empty( $override['id'] ) ? (int) $override['id'] : 0,
			'type'          => 'override',
			'name'          => $ability instanceof \WP_Ability ? (string) $ability->get_label() : $slug,
			'slug'          => $slug,
			'description'   => $ability instanceof \WP_Ability ? (string) $ability->get_description() : __( 'Registered override with no currently loaded ability.', 'acrossai-abilities-manager' ),
			'label'         => '',
			'status'        => '',
			'provider'      => $provider,
			'category'      => $category,
			'category_slug' => $category_slug,
			'provider_kind' => $kind,
			'site_allowed'  => $this->coalesce_bool( $override['site_allowed'] ?? null, true ),
			'readonly'      => $this->coalesce_bool( $override['readonly'] ?? null, $annotations['readonly'] ?? null ),
			'destructive'   => $this->coalesce_bool( $override['destructive'] ?? null, $annotations['destructive'] ?? null ),
			'idempotent'    => $this->coalesce_bool( $override['idempotent'] ?? null, $annotations['idempotent'] ?? null ),
			'show_in_rest'  => $this->coalesce_bool( $override['show_in_rest'] ?? null, $meta['show_in_rest'] ?? null ),
			'mcp_public'    => $this->coalesce_bool( $override['mcp_public'] ?? null, $meta['mcp']['public'] ?? null ),
			'mcp_type'      => $this->coalesce_text( $override['mcp_type'] ?? '', $meta['mcp']['type'] ?? '' ),
			'has_override'  => is_array( $override ),
		);
	}

	/**
	 * Builds the data array for a single custom ability row.
	 *
	 * Extracts custom ability data from the database and formats it for display.
	 * Increments the custom ability counter for the stats bar.
	 *
	 * @param string               $slug   Custom ability slug.
	 * @param array<string, mixed> $custom Custom ability data from database.
	 * @return array<string, mixed> Flat item array ready for column_*() methods.
	 */
	private function build_custom_item( string $slug, array $custom ): array {
		++$this->provider_counts['custom'];

		return array(
			'id'            => (int) ( $custom['id'] ?? 0 ),
			'type'          => 'custom',
			'name'          => $custom['label'] ?? $slug,
			'slug'          => $slug,
			'description'   => $custom['description'] ?? '',
			'label'         => $custom['label'] ?? '',
			'status'        => $custom['status'] ?? 'active',
			'provider'      => 'custom',
			'category'      => $custom['category'] ?? '',
			'category_slug' => $custom['category'] ?? '',
			'provider_kind' => 'custom',
			'site_allowed'  => true,
			'readonly'      => false,
			'destructive'   => false,
			'idempotent'    => false,
			'show_in_rest'  => false,
			'mcp_public'    => false,
			'mcp_type'      => '',
			'has_override'  => false,
		);
	}

	/**
	 * Applies search and provider-kind filters to the item list.
	 *
	 * Search is a case-insensitive substring match against name, slug,
	 * description, category label, and category slug. Provider filter
	 * matches against the computed `provider_kind` value ('core', 'plugin',
	 * 'theme') rather than the raw provider string.
	 *
	 * @param array<int, array<string, mixed>> $items Full unfiltered item list.
	 * @return array<int, array<string, mixed>> Filtered item list with sequential integer keys.
	 */
	private function filter_items( array $items ): array {
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provider = isset( $_REQUEST['provider'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['provider'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Apply substring search across multiple fields when a search term is present.
		if ( '' !== $search ) {
			$items = array_filter(
				$items,
				static function ( array $item ) use ( $search ): bool {
					return false !== stripos( $item['name'], $search )
						|| false !== stripos( $item['slug'], $search )
						|| false !== stripos( $item['description'], $search )
						|| false !== stripos( $item['category'], $search )
						|| false !== stripos( $item['category_slug'], $search );
				}
			);
		}

		// Apply provider-kind filter when a specific kind is selected (not 'all').
		if ( '' !== $provider && 'all' !== $provider ) {
			$items = array_filter(
				$items,
				static function ( array $item ) use ( $provider ): bool {
					return $provider === $item['provider_kind'];
				}
			);
		}

		return array_values( $items );
	}

	/**
	 * Usort() comparator for the item list.
	 *
	 * Boolean columns require special handling because PHP's spaceship operator
	 * does not sort nullable booleans in the expected yes → no → unknown order.
	 * They are converted to integers (1, 0, -1) by sort_bool_value() first.
	 *
	 * @param array<string, mixed> $left  Left item.
	 * @param array<string, mixed> $right Right item.
	 * @return int Negative, zero, or positive as required by usort().
	 */
	private function sort_items( array $left, array $right ): int {
		$orderby      = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'name'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order        = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$bool_columns = array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'mcp_public' );

		// Boolean columns need integer coercion so they sort in yes/no/unknown order.
		if ( in_array( $orderby, $bool_columns, true ) ) {
			$value_a = $this->sort_bool_value( $left[ $orderby ] ?? null );
			$value_b = $this->sort_bool_value( $right[ $orderby ] ?? null );
		} else {
			$value_a = strtolower( (string) ( $left[ $orderby ] ?? '' ) );
			$value_b = strtolower( (string) ( $right[ $orderby ] ?? '' ) );
		}

		$result = $value_a <=> $value_b;

		// Negate the result to switch between ascending and descending order.
		return 'desc' === $order ? -$result : $result;
	}

	/**
	 * Converts a nullable boolean to a translated display string.
	 *
	 * Returns an em-dash for null (the "not set" tri-state), 'Yes' for true,
	 * and 'No' for false.
	 *
	 * @param bool|null $value Value to render.
	 * @return string Translated 'Yes', 'No', or '&mdash;'.
	 */
	private function render_bool_value( ?bool $value ): string {
		// Null means the field has no override and no live default — show a dash.
		if ( null === $value ) {
			return '&mdash;';
		}

		return $value ? esc_html__( 'Yes', 'acrossai-abilities-manager' ) : esc_html__( 'No', 'acrossai-abilities-manager' );
	}


	/**
	 * Converts a nullable boolean to a sortable integer.
	 *
	 * Produces 1 (true), 0 (false), or -1 (null/unknown) so that usort()
	 * can produce a consistent and meaningful sort order for boolean columns.
	 *
	 * @param bool|null $value Value to convert.
	 * @return int 1, 0, or -1.
	 */
	private function sort_bool_value( ?bool $value ): int {
		// Put null (unknown) values at the end of any sort by using -1.
		if ( null === $value ) {
			return -1;
		}

		return $value ? 1 : 0;
	}

	/**
	 * Returns the override boolean if non-null, otherwise falls back to the provided default.
	 *
	 * Ensures the fallback is cast to bool (or kept null) so the return type
	 * is always ?bool regardless of what type the caller passes as $fallback.
	 *
	 * @param mixed $override Override value (may be null).
	 * @param mixed $fallback Fallback value from live ability metadata.
	 * @return bool|null Resolved nullable boolean.
	 */
	private function coalesce_bool( $override, $fallback ): ?bool {
		return null === $override ? ( is_bool( $fallback ) ? $fallback : null ) : (bool) $override;
	}

	/**
	 * Returns the override text if non-empty, otherwise falls back to the provided default.
	 *
	 * Both values are trimmed before comparison. Non-string fallbacks are
	 * returned as empty string rather than causing a type error.
	 *
	 * @param mixed $override Override string value (may be empty).
	 * @param mixed $fallback Fallback string from live ability metadata.
	 * @return string Resolved text value.
	 */
	private function coalesce_text( $override, $fallback ): string {
		$override = is_string( $override ) ? trim( $override ) : '';

		// Return the override string when it is non-empty.
		if ( '' !== $override ) {
			return $override;
		}

		return is_string( $fallback ) ? trim( $fallback ) : '';
	}

	/**
	 * Looks up the human-readable label for an ability category slug.
	 *
	 * Returns an empty string for an empty slug. Falls back to the raw slug
	 * when no WP_Ability_Category object can be found for it.
	 *
	 * @param string $slug Category slug to look up.
	 * @return string Human-readable label, raw slug as fallback, or empty string.
	 */
	private function category_label( string $slug ): string {
		// No label to look up when the slug is empty.
		if ( '' === $slug ) {
			return '';
		}

		$category = $this->categories[ $slug ] ?? null;

		// Return the label when a WP_Ability_Category is found.
		if ( $category instanceof \WP_Ability_Category ) {
			return $category->get_label();
		}

		return $slug;
	}

	/**
	 * Infers the provider identifier from an ability slug's namespace segment.
	 *
	 * The namespace is the first path segment before the first `/`. Known
	 * WordPress core namespaces map to `core`; active theme slugs map to
	 * `theme:<slug>`; everything else is returned as-is (plugin slug).
	 *
	 * @param string $slug Ability slug to inspect.
	 * @return string Provider identifier such as 'core', 'theme:my-theme', or a plugin slug.
	 */
	private function detect_provider( string $slug ): string {
		$namespace = sanitize_key( explode( '/', $slug )[0] ?? '' );

		// Map well-known WordPress core namespaces to a single canonical 'core' provider.
		if ( in_array( $namespace, array( 'wordpress', 'wp', 'core' ), true ) ) {
			return 'core';
		}

		$stylesheet = sanitize_key( (string) get_stylesheet() );
		$template   = sanitize_key( (string) get_template() );

		// Match against both the child theme and parent theme slugs.
		if ( in_array( $namespace, array( $stylesheet, $template ), true ) ) {
			return 'theme:' . $namespace;
		}

		// Fall back to the raw namespace, or 'unknown' when the slug has no namespace.
		return '' !== $namespace ? $namespace : 'unknown';
	}

	/**
	 * Maps a provider identifier to one of three canonical kinds: 'core', 'theme', 'plugin'.
	 *
	 * Used by render_stats_bar() for tab counts and by filter_items() when
	 * the provider filter is active.
	 *
	 * @param string $provider Full provider string (e.g. 'core', 'theme:my-theme', 'my-plugin').
	 * @return string One of 'core', 'theme', or 'plugin'.
	 */
	private function provider_kind( string $provider ): string {
		// The literal string 'core' identifies WordPress core abilities.
		if ( 'core' === $provider ) {
			return 'core';
		}

		// Theme providers always start with the 'theme:' prefix.
		if ( 0 === strpos( $provider, 'theme:' ) ) {
			return 'theme';
		}

		// Everything else is treated as a plugin provider.
		return 'plugin';
	}

	/**
	 * Returns a human-readable label for a provider identifier.
	 *
	 * Used in the Provider column cell to present a friendly name instead of
	 * the raw internal provider string.
	 *
	 * @param string $provider Provider identifier to describe.
	 * @return string Translated label such as 'Core', 'Theme: my-theme', 'Plugin: my-plugin'.
	 */
	private function provider_label( string $provider ): string {
		if ( 'core' === $provider ) {
			return __( 'Core', 'acrossai-abilities-manager' );
		}

		// Strip the 'theme:' prefix and present only the theme slug in the label.
		if ( 0 === strpos( $provider, 'theme:' ) ) {
			// translators: %s is the theme name.
			return sprintf( __( 'Theme: %s', 'acrossai-abilities-manager' ), substr( $provider, 6 ) );
		}

		// Show 'Unknown' for abilities whose provider could not be detected.
		if ( '' === $provider || 'unknown' === $provider ) {
			return __( 'Unknown', 'acrossai-abilities-manager' );
		}

		// translators: %s is the plugin name.
		return sprintf( __( 'Plugin: %s', 'acrossai-abilities-manager' ), $provider );
	}

	/**
	 * Builds the URL for a provider filter tab, preserving the current sort parameters.
	 *
	 * Copies `orderby` and `order` from the current request into the tab URL
	 * so the sort order is not lost when the user switches between tabs.
	 *
	 * @param string $provider Provider kind to filter by ('all', 'core', 'plugin', or 'theme').
	 * @return string Absolute admin URL for the provider tab.
	 */
	private function provider_tab_url( string $provider ): string {
		$args = array(
			'page'     => 'acrossai-abilities-manager',
			'provider' => $provider,
		);

		// Preserve the current sort column when switching provider tabs.
		if ( isset( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['orderby'] = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// Preserve the current sort direction when switching provider tabs.
		if ( isset( $_REQUEST['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['order'] = sanitize_key( wp_unslash( $_REQUEST['order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return add_query_arg( $args, admin_url( 'tools.php' ) );
	}
}
