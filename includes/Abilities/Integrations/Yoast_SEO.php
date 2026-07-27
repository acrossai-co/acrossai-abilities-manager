<?php
/**
 * Yoast SEO — third-party integration (Feature 060 extension, 2026-07-27).
 *
 * Yoast SEO registers three WP Abilities API abilities under its own category
 * `yoast-seo` (see wordpress-seo/src/abilities/user-interface/abilities-integration.php
 * as of v28.1):
 *
 *   - yoast-seo/get-seo-scores
 *   - yoast-seo/get-readability-scores
 *   - yoast-seo/get-inclusive-language-scores
 *
 * ## Why the pattern deviates from ACF
 *
 * ACF (see `ACF.php`) has a SINGLE master-switch filter
 * (`acf/settings/enable_acf_ai`) that gates all its AI abilities in one place.
 * The ACF integration attaches that filter when the toggle is ON.
 *
 * Yoast has no such filter. Each of its three abilities is independently gated
 * by a separate analysis feature flag inside Yoast's own settings (Keyphrase
 * Analysis, Readability Analysis, Inclusive Language Analysis). Flipping those
 * flags via a filter would silently mutate the site admin's Yoast settings and
 * change Yoast's non-abilities UX — clearly not what an integration toggle
 * should do.
 *
 * Instead, this integration uses a **kill-switch** model:
 *
 *   - `enable_filter()` is a no-op. When the integration toggle is ON, we let
 *     Yoast register whichever abilities its own feature settings permit.
 *   - `maybe_unregister()` (new hook, wired from the constructor at
 *     `wp_abilities_api_init @ P200`, after Yoast's default-priority
 *     registration at P10) reads the toggle state and, when OFF, calls
 *     `wp_unregister_ability()` on Yoast's abilities. Guarded per-ability with
 *     `wp_get_ability()` so we never try to unregister an ability Yoast never
 *     registered (Yoast's analysis-feature-flag gate).
 *
 * User-facing behaviour matches the ACF pattern: toggle ON = Yoast abilities
 * available; toggle OFF = Yoast abilities gone. Only the mechanism differs.
 *
 * ## Category / tab_group slug
 *
 * `TAB_GROUP = 'yoast'` (NOT `'yoast-seo'`). Reason: Yoast pre-registers the
 * `yoast-seo` slug as a WP ability category on `wp_abilities_api_categories_init`.
 * If we used the same slug, our synthetic display rows (which carry
 * `category = <slug>`) would successfully pass WP core's category-existence
 * check at `wp_register_ability()` and leak into `wp_get_abilities()` with
 * their fail-closed callbacks — see `BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION`
 * and the synthetic-row lifecycle note in
 * `AcrossAI_Integration_Ability_Base::push_definition()`. Using a distinct
 * slug (`yoast`) keeps that category unregistered, which is the design intent.
 *
 * The tab label derives from the slug via `titleCaseTabLabel()` on the JS
 * side: `'yoast'` → `'Yoast'`.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Abilities/Integrations
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Integrations;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Config;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations\AcrossAI_Integration_Ability_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Third-party integration wrapper for Yoast SEO's AI abilities.
 *
 * @since 0.1.0
 */
class Yoast_SEO extends AcrossAI_Integration_Ability_Base {

	/**
	 * The tab_group identifier for the "Yoast" tab on the Ability Library page.
	 *
	 * Deliberately `'yoast'` (not `'yoast-seo'`) to avoid colliding with Yoast's
	 * own pre-registered ability category — see the class docblock for the
	 * synthetic-row lifecycle rationale.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const TAB_GROUP = 'yoast';

	/**
	 * The exact ability names Yoast SEO registers with WP core.
	 *
	 * Mirrored from wordpress-seo/src/abilities/user-interface/abilities-integration.php
	 * (v28.1). Kept in sync manually because Yoast doesn't publish a public
	 * constant list. Used only by `maybe_unregister()` — display rows come
	 * from `abilities()` below.
	 *
	 * @since 0.1.0
	 * @var string[]
	 */
	private const YOAST_ABILITY_NAMES = array(
		'yoast-seo/get-seo-scores',
		'yoast-seo/get-readability-scores',
		'yoast-seo/get-inclusive-language-scores',
	);

	/**
	 * Wire the kill-switch hook alongside the base class's two hooks.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		parent::__construct();
		// Runs after Yoast's default-priority (P10) ability registration so we
		// can inspect wp_get_abilities() to find what Yoast actually registered
		// this request, then unregister on the OFF path.
		add_action( 'wp_abilities_api_init', array( $this, 'maybe_unregister' ), 200 );
	}

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
		return __( 'Yoast SEO', 'acrossai-abilities-manager' );
	}

	/**
	 * Whether Yoast SEO is loaded on the current site.
	 *
	 * Compound check per SEC-002: `defined()` on Yoast's version constant AND
	 * `function_exists()` on Yoast's main container accessor. A single-symbol
	 * check would be spoofable by any other plugin defining `WPSEO_VERSION`.
	 *
	 * @since  0.1.0
	 * @return bool
	 */
	protected function is_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) && function_exists( 'YoastSEO' );
	}

	/**
	 * No-op — Yoast has no single master enable filter.
	 *
	 * Unlike ACF (which exposes `acf/settings/enable_acf_ai`), Yoast gates
	 * each of its three abilities behind an independent analysis feature flag
	 * in Yoast's own settings. Flipping those flags via a filter would
	 * silently mutate the admin's Yoast configuration and change Yoast's
	 * non-abilities UX — out of scope for an integration toggle. The
	 * kill-switch in `maybe_unregister()` handles the OFF path.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	protected function enable_filter(): void {
		// Intentional no-op. See docblock.
	}

	/**
	 * Kill-switch hook — unregisters Yoast's abilities when the integration
	 * toggle is OFF.
	 *
	 * Wired from the constructor at `wp_abilities_api_init @ P200` so it runs
	 * AFTER Yoast's registration at the default P10. Guarded per-ability with
	 * `wp_get_ability()` because Yoast's per-feature gating may mean any subset
	 * of the three abilities is registered on a given request.
	 *
	 * Symmetric with the ACF integration's user-facing behaviour: toggle OFF =
	 * abilities absent from `wp_get_abilities()`, Custom Abilities table, and
	 * MCP `discover-abilities`.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function maybe_unregister(): void {
		if ( ! $this->is_plugin_active() ) {
			return;
		}
		if ( AcrossAI_Ability_Library_Config::is_integration_enabled( $this->slug() ) ) {
			return;
		}
		if ( ! function_exists( 'wp_unregister_ability' ) || ! function_exists( 'wp_get_ability' ) ) {
			return;
		}

		foreach ( self::YOAST_ABILITY_NAMES as $ability_name ) {
			if ( wp_get_ability( $ability_name ) ) {
				wp_unregister_ability( $ability_name );
			}
		}
	}

	/**
	 * Fixed readonly list of the abilities Yoast SEO exposes when its own
	 * analysis features are enabled.
	 *
	 * Names + labels mirror
	 * `wordpress-seo/src/abilities/user-interface/abilities-integration.php`
	 * (v28.1). Descriptions augment Yoast's own descriptions with a note about
	 * the per-ability gating in Yoast's settings so admins know why an ability
	 * may not appear even when this integration is ON.
	 *
	 * @since  0.1.0
	 * @return array<int, array{slug: string, label: string, description: string}>
	 */
	protected function abilities(): array {
		return array(
			array(
				'slug'        => 'yoast-seo/get-seo-scores',
				'label'       => __( 'Get SEO Scores', 'acrossai-abilities-manager' ),
				'description' => __(
					'Get the SEO scores for the most recently modified posts. Requires Yoast SEO\'s Keyphrase Analysis feature to be enabled in the Yoast SEO settings.',
					'acrossai-abilities-manager'
				),
			),
			array(
				'slug'        => 'yoast-seo/get-readability-scores',
				'label'       => __( 'Get Readability Scores', 'acrossai-abilities-manager' ),
				'description' => __(
					'Get the readability scores for the most recently modified posts. Requires Yoast SEO\'s Readability Analysis feature to be enabled in the Yoast SEO settings.',
					'acrossai-abilities-manager'
				),
			),
			array(
				'slug'        => 'yoast-seo/get-inclusive-language-scores',
				'label'       => __( 'Get Inclusive Language Scores', 'acrossai-abilities-manager' ),
				'description' => __(
					'Get the inclusive language scores for the most recently modified posts. Requires Yoast SEO\'s Inclusive Language Analysis feature to be enabled in the Yoast SEO settings.',
					'acrossai-abilities-manager'
				),
			),
		);
	}
}
