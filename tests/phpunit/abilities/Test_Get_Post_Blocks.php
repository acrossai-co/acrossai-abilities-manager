<?php
/**
 * Feature 066 — source-inspection tests for acrossai/get-post-blocks.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Get_Post_Blocks extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Content/Get_Post_Blocks.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'acrossai/get-post-blocks'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-content'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_readonly(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_delegates_to_block_tree_read(): void {
		$this->assertStringContainsString( "Block_Tree::parse_post_blocks( \$post_id, 'read' )", $this->src );
		$this->assertStringContainsString( 'Block_Tree::annotate_with_paths(', $this->src );
	}

	public function test_input_schema_requires_post_id(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id' )", $this->src );
	}

	public function test_output_exposes_blocks_and_total(): void {
		$this->assertStringContainsString( "'blocks'  => array( 'type' => 'array' )", $this->src );
		$this->assertStringContainsString( "'total'   => array( 'type' => 'integer' )", $this->src );
	}
}
