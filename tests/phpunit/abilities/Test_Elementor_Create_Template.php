<?php
/** Feature 067 — Create_Template tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Create_Template extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Create_Template.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-create-template'", $this->src ); }
	public function test_type_enum_includes_common(): void { $this->assertStringContainsString( "'page', 'section', 'popup', 'header', 'footer', 'single', 'archive'", $this->src ); }
	public function test_requires_title_and_type(): void { $this->assertStringContainsString( "'required'   => array( 'title', 'type' )", $this->src ); }
	public function test_sets_taxonomy_term(): void { $this->assertStringContainsString( 'wp_set_object_terms(', $this->src ); }
	public function test_seeds_elementor_data(): void { $this->assertStringContainsString( 'Document_Repository::save_data(', $this->src ); }
}
