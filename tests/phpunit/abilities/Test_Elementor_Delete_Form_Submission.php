<?php
/** Feature 067 — Delete_Form_Submission tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Delete_Form_Submission extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Delete_Form_Submission.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/delete-form-submission'", $this->src ); }
	public function test_pro_gate(): void { $this->assertStringContainsString( 'assert_elementor_pro_available()', $this->src ); }
	public function test_requires_confirm(): void { $this->assertStringContainsString( "'required' => array( 'submission_id', 'confirm' )", $this->src ); }
	public function test_force_delete_error_when_no_confirm(): void { $this->assertStringContainsString( "'force_delete_required'", $this->src ); }
	public function test_deletes_values_and_submission(): void { $this->assertStringContainsString( '$wpdb->delete( $vals,', $this->src ); $this->assertStringContainsString( '$wpdb->delete( $table,', $this->src ); }
	public function test_destructive(): void { $this->assertStringContainsString( "'destructive' => true", $this->src ); }
}
