<?php
/**
 * Feature 067 — Add_Post_Tabs tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Add_Post_Tabs extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Add_Post_Tabs.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug(): void {
		$this->assertStringContainsString( "'elementor/add-post-tabs'", $this->src );
	}

	public function test_requires_post_id_and_tabs(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id', 'tabs' )", $this->src );
	}

	public function test_builds_nested_tabs_widget(): void {
		$this->assertStringContainsString( "'widgetType' => 'nested-tabs'", $this->src );
	}

	public function test_each_tab_contains_posts_widget(): void {
		$this->assertStringContainsString( "'widgetType' => 'posts'", $this->src );
	}

	public function test_returns_tab_count(): void {
		$this->assertStringContainsString( "'tab_count'", $this->src );
	}

	public function test_defense_in_depth_gate(): void {
		$this->assertStringContainsString( 'assert_elementor_available()', $this->src );
	}
}
