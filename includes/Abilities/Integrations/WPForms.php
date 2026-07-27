<?php
/**
 * WPForms — third-party integration (Feature 060 extension).
 *
 * WPForms exposes WP Abilities API abilities in TWO tiers:
 *
 *   - **Reads** (auto-enabled) — always available when WPForms is active; no
 *     admin gate. Includes listing forms, listing entries, reading form
 *     schemas, etc. Our integration does NOT gate reads — they pass through
 *     transparently regardless of this integration's toggle state.
 *
 *   - **Writes** (gated) — creating, updating, and deleting forms + entries.
 *     Gated by the WPForms-provided filter
 *     `wpforms_integrations_abilities_allow_write`. Defaults to false in
 *     WPForms itself (WPForms won't register the write abilities unless the
 *     filter returns true). This integration's toggle attaches
 *     `__return_true` to that filter when ON.
 *
 * ## Pattern: enable-filter (like ACF, unlike Yoast)
 *
 * WPForms has a SINGLE master filter for the write tier — same shape as ACF's
 * `acf/settings/enable_acf_ai`. So this integration uses the classic
 * enable-filter model:
 *
 *   - `enable_filter()` attaches `wpforms_integrations_abilities_allow_write`
 *     → `__return_true` when the integration toggle is ON.
 *   - `enable_filter()` does nothing when toggle is OFF, so WPForms's default
 *     (false) applies and the write abilities stay unregistered.
 *
 * No kill-switch is needed because the write abilities never register in the
 * first place unless our filter fires. Reads are outside the gate entirely
 * and are always available while WPForms is active.
 *
 * ## Category / tab_group slug
 *
 * `TAB_GROUP = 'wpforms'`. WPForms's own ability categories almost certainly
 * live under a `wpforms/*` namespace or similar — using the plain slug
 * `'wpforms'` for our card keeps it clean and avoids collision with WPForms's
 * pre-registered category slugs (following the synthetic-row lifecycle rule
 * documented in `AcrossAI_Integration_Ability_Base::push_definition()` and in
 * `BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION`).
 *
 * Tab label auto-derives from the slug: `'wpforms'` → `'Wpforms'`.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Abilities/Integrations
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Integrations;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations\AcrossAI_Integration_Ability_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Third-party integration wrapper for WPForms's write-tier abilities.
 *
 * @since 0.1.0
 */
class WPForms extends AcrossAI_Integration_Ability_Base {

	/**
	 * The tab_group identifier for the "Wpforms" tab on the Ability Library page.
	 *
	 * Third-party plugins wanting to add cards to this tab reference this
	 * constant instead of hardcoding `'wpforms'`.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const TAB_GROUP = 'wpforms';

	/**
	 * Category / tab_group identifier.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	protected function slug(): string {
		return self::TAB_GROUP;
	}

	/**
	 * Human-readable label for the card and tab.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	protected function label(): string {
		return __( 'WPForms', 'acrossai-abilities-manager' );
	}

	/**
	 * Whether WPForms is loaded on the current site.
	 *
	 * Compound check per SEC-002: `defined()` on WPForms's version constant
	 * AND `function_exists()` on the main WPForms container accessor. A
	 * single-symbol check would be spoofable by any other plugin defining
	 * `WPFORMS_VERSION`.
	 *
	 * Covers both WPForms Lite and WPForms Pro — both define
	 * `WPFORMS_VERSION` and the `wpforms()` container function.
	 *
	 * @since  0.1.0
	 * @return bool
	 */
	protected function is_plugin_active(): bool {
		return defined( 'WPFORMS_VERSION' ) && function_exists( 'wpforms' );
	}

	/**
	 * Attach WPForms's write-tier enable filter.
	 *
	 * WPForms internally checks `apply_filters( 'wpforms_integrations_abilities_allow_write', false )`
	 * before registering its write-tier abilities. Default is false → writes
	 * don't register. Attaching `__return_true` here flips it, so WPForms
	 * registers its create/update/delete abilities on the current request.
	 *
	 * Only the WRITE filter is attached. Reads are auto-enabled by WPForms
	 * regardless of any filter, so no filter attachment is needed for the
	 * read tier — this is documented in the abilities() list below.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function enable_filter(): void {
		add_filter( 'wpforms_integrations_abilities_allow_write', '__return_true' );
	}

	/**
	 * Fixed readonly list of the ability tiers WPForms exposes.
	 *
	 * Two rows — one per tier — because WPForms's abilities split cleanly
	 * along the read/write boundary and the admin needs to understand which
	 * tier this toggle gates. The read row is INFORMATIONAL only (reads are
	 * auto-enabled by WPForms whenever the plugin is active — this toggle
	 * doesn't affect them). The write row describes what enabling this
	 * toggle actually unlocks.
	 *
	 * @since  0.1.0
	 * @return array<int, array{slug: string, label: string, description: string}>
	 */
	protected function abilities(): array {
		return array(
			array(
				'slug'        => 'wpforms/reads',
				'label'       => __( 'Read abilities (auto-enabled)', 'acrossai-abilities-manager' ),
				'description' => __(
					'Read-only abilities exposed by WPForms — listing forms, reading form schemas, listing entries, and similar queries. WPForms enables these automatically whenever the plugin is active; this integration toggle does NOT gate them and they remain available regardless of the toggle state below.',
					'acrossai-abilities-manager'
				),
			),
			array(
				'slug'        => 'wpforms/writes',
				'label'       => __( 'Write abilities (gated by this toggle)', 'acrossai-abilities-manager' ),
				'description' => __(
					'Mutation abilities exposed by WPForms — creating, updating, and deleting forms and entries. Gated by the WPForms filter wpforms_integrations_abilities_allow_write (default: false). Flip the toggle above ON to attach __return_true to that filter so WPForms registers the write-tier abilities on subsequent requests.',
					'acrossai-abilities-manager'
				),
			),
		);
	}
}
