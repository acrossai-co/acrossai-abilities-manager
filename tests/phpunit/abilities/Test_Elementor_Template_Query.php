<?php
/**
 * Feature 067 — Template_Query utility tests (source-inspection).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Template_Query extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Elementor/Template_Query.php'
		);
	}

	public function test_uses_elementor_library_cpt_and_taxonomy(): void {
		$this->assertStringContainsString( "public const CPT = 'elementor_library'", $this->src );
		$this->assertStringContainsString( "public const TYPE_TAX = 'elementor_library_type'", $this->src );
	}

	public function test_query_uses_wp_query(): void {
		$this->assertStringContainsString( 'new WP_Query', $this->src );
	}

	public function test_score_pattern_match_scores_title_widget_types(): void {
		$this->assertStringContainsString( 'public static function score_pattern_match(', $this->src );
		$this->assertStringContainsString( 'walk_tree', $this->src );
	}

	public function test_rank_by_pattern_returns_top_n(): void {
		$this->assertStringContainsString( 'public static function rank_by_pattern(', $this->src );
		$this->assertStringContainsString( 'array_slice(', $this->src );
	}

	public function test_to_summary_reads_conditions_meta(): void {
		$this->assertStringContainsString( "_elementor_conditions", $this->src );
	}
}
