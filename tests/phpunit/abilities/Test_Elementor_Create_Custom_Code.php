<?php
/** Feature 067 — Create_Custom_Code tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Create_Custom_Code extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Create_Custom_Code.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-create-custom-code'", $this->src ); }
	public function test_requires_title_code_location(): void { $this->assertStringContainsString( "'required' => array( 'title', 'code', 'location' )", $this->src ); }
	public function test_pro_gate(): void { $this->assertStringContainsString( 'assert_elementor_pro_available()', $this->src ); }
	public function test_inserts_snippet_cpt(): void { $this->assertStringContainsString( 'List_Custom_Code::CPT', $this->src ); }
	public function test_writes_location_meta(): void { $this->assertStringContainsString( "'_elementor_snippet_location'", $this->src ); }
}
