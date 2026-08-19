<?php
/** Feature 067 — List_Custom_Code tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_List_Custom_Code extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/List_Custom_Code.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'elementor/list-custom-code'", $this->src ); }
	public function test_uses_elementor_snippet_cpt(): void { $this->assertStringContainsString( "public const CPT = 'elementor_snippet'", $this->src ); }
	public function test_pro_gate(): void { $this->assertStringContainsString( 'Document_Repository::assert_elementor_pro_available()', $this->src ); }
	public function test_location_enum(): void { $this->assertStringContainsString( "'head', 'body_start', 'body_end', 'footer'", $this->src ); }
	public function test_readonly(): void { $this->assertStringContainsString( "'readonly' => true", $this->src ); }
}
