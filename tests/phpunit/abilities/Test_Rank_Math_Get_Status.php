<?php
/**
 * Feature 069 — source-inspection tests for rank-math/get-status.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Status_Repository;
use WP_UnitTestCase;

class Test_Rank_Math_Get_Status extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/Get_Status.php'
		);
	}

	public function test_extends_rank_math_base(): void {
		$this->assertStringContainsString( 'extends Base_Rank_Math_Ability', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "return 'get-status';", $this->src );
	}

	public function test_declares_sub_group(): void {
		$this->assertStringContainsString( "return 'rank-math-status';", $this->src );
	}

	/**
	 * Rank Math gates these panels on manage_options via
	 * Rest_Helper::can_manage_options(), so there is no granular capability to
	 * compose. An empty return is deliberate, not a missing mapping.
	 */
	public function test_uses_floor_only_capability(): void {
		$this->assertMatchesRegularExpression(
			"/function rank_math_cap\(\): string \{\s*return '';/",
			$this->src
		);
	}

	public function test_readonly_annotations(): void {
		$this->assertStringContainsString( "'readonly' => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
		$this->assertStringContainsString( "'idempotent' => true", $this->src );
	}

	public function test_does_not_require_confirmation(): void {
		$this->assertStringNotContainsString( 'requires_confirmation', $this->src );
	}

	/**
	 * FR-015 — third-party symbols belong in Utilities/RankMath only. Asserting
	 * this mechanically is what keeps the Rank Math API surface confined to the
	 * helper layer.
	 *
	 * Our own namespace segments (Abilities\RankMath and
	 * Abilities\Utilities\RankMath) are stripped first, since they legitimately
	 * contain the token.
	 */
	public function test_no_direct_rank_math_references(): void {
		$stripped = str_replace(
			array( 'Abilities\\Utilities\\RankMath', 'Abilities\\RankMath' ),
			'',
			$this->src
		);
		$this->assertStringNotContainsString( 'RankMath', $stripped );
	}

	/**
	 * The input enum and the repository's panel list must not drift apart — that
	 * is the failure mode a consolidated enum ability introduces.
	 */
	public function test_enum_is_sourced_from_the_repository(): void {
		$this->assertStringContainsString( "'enum'        => Status_Repository::PANELS", $this->src );
	}

	public function test_panel_list_is_complete(): void {
		$this->assertSame(
			array( 'status', 'tools', 'import_export', 'version_control', 'google' ),
			Status_Repository::PANELS
		);
	}

	public function test_rejects_unknown_panel(): void {
		$this->assertStringContainsString( "'invalid_input'", $this->src );
		$this->assertStringContainsString( 'in_array( $panel, Status_Repository::PANELS, true )', $this->src );
	}

	/**
	 * Console::get_sites() makes a live googleapis.com request, so it must never
	 * be on the default path.
	 */
	public function test_site_listing_is_opt_in(): void {
		$this->assertMatchesRegularExpression(
			"/'include_sites' => array\(\s*'type'        => 'boolean',\s*'default'     => false,/",
			$this->src
		);
	}
}
