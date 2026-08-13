<?php
/** Feature 067 — Get_Custom_Code tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Get_Custom_Code extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Get_Custom_Code.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-get-custom-code'", $this->src ); }
	public function test_requires_snippet_id(): void { $this->assertStringContainsString( "'required' => array( 'snippet_id' )", $this->src ); }
	public function test_pro_gate(): void { $this->assertStringContainsString( 'assert_elementor_pro_available()', $this->src ); }
	public function test_returns_code(): void { $this->assertStringContainsString( "'code' => (string) \$post->post_content", $this->src ); }
}
