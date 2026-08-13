<?php
/** Feature 067 — Get_Form_Submission tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Get_Form_Submission extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Form_Submission.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-get-form-submission'", $this->src ); }
	public function test_pro_gate(): void { $this->assertStringContainsString( 'assert_elementor_pro_available()', $this->src ); }
	public function test_requires_submission_id(): void { $this->assertStringContainsString( "'required' => array( 'submission_id' )", $this->src ); }
	public function test_optionally_includes_values(): void { $this->assertStringContainsString( 'List_Form_Submissions::fetch_values(', $this->src ); }
}
