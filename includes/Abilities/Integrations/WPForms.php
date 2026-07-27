<?php
/**
 * WPForms — third-party integration (Feature 060 extension).
 *
 * WPForms exposes WP Abilities API abilities in TWO tiers:
 *
 *   - **Reads** (auto-enabled) — always available when WPForms is active; no
 *     admin gate. Includes listing forms, reading form schemas, and similar
 *     queries. Our integration does NOT gate reads — they pass through
 *     transparently regardless of this integration's toggle state.
 *
 *   - **Writes** (gated) — Add Field, Create Form, Update Field, Update Form
 *     Settings, and similar mutations. Gated by the WPForms-provided filter
 *     `wpforms_integrations_abilities_allow_write` (default: false).
 *
 * ## What the write toggle actually does (subtle)
 *
 * The `wpforms_integrations_abilities_allow_write` filter DOES NOT gate
 * registration. WPForms registers ALL 8 abilities (reads + writes)
 * unconditionally on every request — they appear in `wp_get_abilities()` and
 * in the Custom Abilities admin table regardless of the filter value. See
 * `wpforms-lite/src/Integrations/Abilities/Abilities.php::write_enabled()`
 * (line 562) for the filter reference and lines 599 + 722 for the two things
 * the filter actually gates:
 *
 *   1. **`mcp.public` flag** on each write ability's `meta.mcp` block. When
 *      the filter returns false, `mcp.public=false` and the write abilities
 *      are HIDDEN from MCP `discover-abilities` — external MCP clients don't
 *      see them.
 *   2. **Execution permission** via WPForms's `check_write_gate()` inside
 *      each write ability's `permission_callback`. When the filter returns
 *      false, calling a write ability returns `WP_Error 403
 *      wpforms_writes_disabled`.
 *
 * So: our toggle controls **MCP visibility + execution permission**, NOT
 * registration. The Custom Abilities admin table will keep showing all 8
 * abilities regardless — that's WPForms's design, not a bug in this
 * integration.
 *
 * ## Pattern: enable-filter (like ACF)
 *
 * Even though the semantics differ from ACF (which gates registration
 * itself), the mechanical shape is identical: WPForms has a single master
 * filter for the write tier, and our integration attaches `__return_true` to
 * it when the toggle is ON. So:
 *
 *   - `enable_filter()` attaches `wpforms_integrations_abilities_allow_write`
 *     → `__return_true` when the integration toggle is ON.
 *   - `enable_filter()` does nothing when the toggle is OFF, so WPForms's
 *     default (false via `wpforms_setting()` unless the site admin has
 *     already enabled writes in WPForms's own settings) applies.
 *
 * No kill-switch is needed. Reads are outside the gate entirely and remain
 * available while WPForms is active.
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
					'Mutation abilities exposed by WPForms — Add Field, Create Form, Update Field, Update Form Settings, and similar. Gated by the WPForms filter wpforms_integrations_abilities_allow_write. Flip the toggle above ON to attach __return_true to that filter. Effect: WPForms flips the mcp.public flag on each write ability to true (so MCP clients see them via discover-abilities) AND allows write executions (calls return WP_Error 403 wpforms_writes_disabled when the filter is false). Note: WPForms always REGISTERS these 8 abilities so they will keep appearing in the Custom Abilities admin table regardless of this toggle — the toggle affects MCP visibility and execution permission, not registration.',
					'acrossai-abilities-manager'
				),
			),
		);
	}
}
