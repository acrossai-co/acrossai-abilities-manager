<?php
/**
 * Feature 066 — behavioural tests for the shared Block_Tree utility.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.24
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Block_Tree;
use WP_UnitTestCase;
use WP_Error;

/**
 * Behavioural coverage for every Block_Tree primitive.
 */
class Test_Block_Tree extends WP_UnitTestCase {

	/**
	 * Build a canonical fixture:
	 *   [0] core/columns
	 *     [0, 0] core/column
	 *       [0, 0, 0] core/heading  (content: "H")
	 *       [0, 0, 1] core/paragraph (content: "P1")
	 *     [0, 1] core/column
	 *       [0, 1, 0] core/paragraph (content: "P2")
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fixture(): array {
		$leaf = static function ( string $name, array $attrs ): array {
			return array(
				'blockName'    => $name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		};
		$col_a = array(
			'blockName'    => 'core/column',
			'attrs'        => array(),
			'innerBlocks'  => array(
				$leaf( 'core/heading', array( 'content' => 'H' ) ),
				$leaf( 'core/paragraph', array( 'content' => 'P1' ) ),
			),
			'innerHTML'    => '',
			'innerContent' => array( null, null ),
		);
		$col_b = array(
			'blockName'    => 'core/column',
			'attrs'        => array(),
			'innerBlocks'  => array(
				$leaf( 'core/paragraph', array( 'content' => 'P2' ) ),
			),
			'innerHTML'    => '',
			'innerContent' => array( null ),
		);
		return array(
			array(
				'blockName'    => 'core/columns',
				'attrs'        => array(),
				'innerBlocks'  => array( $col_a, $col_b ),
				'innerHTML'    => '',
				'innerContent' => array( null, null ),
			),
		);
	}

	public function test_validate_block_name_accepts_canonical_shape(): void {
		$this->assertTrue( Block_Tree::validate_block_name( 'core/paragraph' ) );
		$this->assertTrue( Block_Tree::validate_block_name( 'my-plugin/some-block' ) );
		$this->assertTrue( Block_Tree::validate_block_name( 'ns_1/block_1' ) );
	}

	public function test_validate_block_name_rejects_invalid(): void {
		$this->assertFalse( Block_Tree::validate_block_name( '' ) );
		$this->assertFalse( Block_Tree::validate_block_name( 'notaslug' ) );
		$this->assertFalse( Block_Tree::validate_block_name( 'core/' ) );
		$this->assertFalse( Block_Tree::validate_block_name( '/paragraph' ) );
		$this->assertFalse( Block_Tree::validate_block_name( 'core/para graph' ) );
	}

	public function test_get_at_path_returns_expected_nodes(): void {
		$blocks = $this->fixture();
		$root   = Block_Tree::get_at_path( $blocks, array( 0 ) );
		$this->assertIsArray( $root );
		$this->assertSame( 'core/columns', $root['blockName'] );

		$para = Block_Tree::get_at_path( $blocks, array( 0, 0, 1 ) );
		$this->assertIsArray( $para );
		$this->assertSame( 'core/paragraph', $para['blockName'] );
		$this->assertSame( 'P1', $para['attrs']['content'] );
	}

	public function test_get_at_path_returns_null_for_invalid(): void {
		$blocks = $this->fixture();
		$this->assertNull( Block_Tree::get_at_path( $blocks, array() ) );
		$this->assertNull( Block_Tree::get_at_path( $blocks, array( 5 ) ) );
		$this->assertNull( Block_Tree::get_at_path( $blocks, array( 0, 9, 0 ) ) );
	}

	public function test_annotate_with_paths_populates_every_node(): void {
		$annotated = Block_Tree::annotate_with_paths( $this->fixture() );
		$this->assertSame( array( 0 ), $annotated[0]['path'] );
		$this->assertSame( array( 0, 0 ), $annotated[0]['innerBlocks'][0]['path'] );
		$this->assertSame( array( 0, 0, 1 ), $annotated[0]['innerBlocks'][0]['innerBlocks'][1]['path'] );
		$this->assertSame( array( 0, 1, 0 ), $annotated[0]['innerBlocks'][1]['innerBlocks'][0]['path'] );
	}

	public function test_insert_at_root_shifts_siblings(): void {
		$blocks = $this->fixture();
		$new    = array( 'blockName' => 'core/heading', 'attrs' => array( 'content' => 'Top' ) );
		$this->assertTrue( Block_Tree::insert_at_path( $blocks, array(), 0, $new ) );
		$this->assertCount( 2, $blocks );
		$this->assertSame( 'core/heading', $blocks[0]['blockName'] );
		$this->assertSame( 'core/columns', $blocks[1]['blockName'] );
	}

	public function test_insert_nested_appends_when_index_exceeds_count(): void {
		$blocks = $this->fixture();
		$new    = array( 'blockName' => 'core/paragraph', 'attrs' => array( 'content' => 'appended' ) );
		$this->assertTrue( Block_Tree::insert_at_path( $blocks, array( 0, 0 ), 999, $new ) );
		$children = $blocks[0]['innerBlocks'][0]['innerBlocks'];
		$this->assertCount( 3, $children );
		$this->assertSame( 'appended', $children[2]['attrs']['content'] );
	}

	public function test_insert_returns_false_for_invalid_parent_path(): void {
		$blocks = $this->fixture();
		$new    = array( 'blockName' => 'core/paragraph', 'attrs' => array() );
		$this->assertFalse( Block_Tree::insert_at_path( $blocks, array( 9 ), 0, $new ) );
	}

	public function test_remove_returns_removed_node_and_shifts_siblings(): void {
		$blocks  = $this->fixture();
		$removed = Block_Tree::remove_at_path( $blocks, array( 0, 0, 0 ) );
		$this->assertIsArray( $removed );
		$this->assertSame( 'core/heading', $removed['blockName'] );
		$this->assertCount( 1, $blocks[0]['innerBlocks'][0]['innerBlocks'] );
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] );
	}

	public function test_remove_returns_null_for_invalid_path(): void {
		$blocks = $this->fixture();
		$this->assertNull( Block_Tree::remove_at_path( $blocks, array() ) );
		$this->assertNull( Block_Tree::remove_at_path( $blocks, array( 0, 9 ) ) );
	}

	public function test_replace_swaps_block_in_place(): void {
		$blocks     = $this->fixture();
		$replacement = array( 'blockName' => 'core/heading', 'attrs' => array( 'content' => 'REPLACED' ) );
		$this->assertTrue( Block_Tree::replace_at_path( $blocks, array( 0, 0, 1 ), $replacement ) );
		$this->assertSame( 'core/heading', $blocks[0]['innerBlocks'][0]['innerBlocks'][1]['blockName'] );
		$this->assertSame( 'REPLACED', $blocks[0]['innerBlocks'][0]['innerBlocks'][1]['attrs']['content'] );
	}

	public function test_replace_returns_false_for_invalid_path(): void {
		$blocks = $this->fixture();
		$this->assertFalse( Block_Tree::replace_at_path( $blocks, array(), array( 'blockName' => 'core/paragraph' ) ) );
		$this->assertFalse( Block_Tree::replace_at_path( $blocks, array( 9 ), array( 'blockName' => 'core/paragraph' ) ) );
	}

	public function test_move_reparents_atomically(): void {
		$blocks = $this->fixture();
		$result = Block_Tree::move( $blocks, array( 0, 1, 0 ), array( 0, 0 ), 0 );
		$this->assertTrue( $result );
		// The paragraph moved from column B into column A at index 0.
		$this->assertSame( 'core/paragraph', $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['blockName'] );
		$this->assertSame( 'P2', $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['content'] );
		// Column B is now empty.
		$this->assertCount( 0, $blocks[0]['innerBlocks'][1]['innerBlocks'] );
	}

	public function test_move_refuses_descendant_destination(): void {
		$blocks = $this->fixture();
		$result = Block_Tree::move( $blocks, array( 0 ), array( 0, 0 ), 0 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'descendant_destination', $result->get_error_code() );
	}

	public function test_move_refuses_empty_source(): void {
		$blocks = $this->fixture();
		$result = Block_Tree::move( $blocks, array(), array( 0 ), 0 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_path', $result->get_error_code() );
	}

	public function test_move_within_same_parent_adjusts_index(): void {
		// Move column B [0,1] to [0], index 0 within same parent [0].
		$blocks = $this->fixture();
		$result = Block_Tree::move( $blocks, array( 0, 1 ), array( 0 ), 0 );
		$this->assertTrue( $result );
		// Column B is now first child of columns; former col A is second.
		$this->assertSame( 'P2', $blocks[0]['innerBlocks'][0]['innerBlocks'][0]['attrs']['content'] );
		$this->assertSame( 'H', $blocks[0]['innerBlocks'][1]['innerBlocks'][0]['attrs']['content'] );
	}

	// ------------------------------------------------------------------
	// Source-inspection coverage for IO-adjacent methods (parse_post_blocks,
	// assert_post_type_editable). These cannot be exercised behaviourally
	// under the unit-only bootstrap (no factory/no real WP env) so we assert
	// the code structure — matching the convention established by
	// Test_Add_Post_Meta and other content-ability tests.
	// ------------------------------------------------------------------

	/**
	 * The Block_Tree source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Utilities/Block_Tree.php'
		);
	}

	public function test_source_declares_forbidden_post_types(): void {
		foreach ( array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ) as $forbidden ) {
			$this->assertStringContainsString( "'{$forbidden}'", $this->src );
		}
	}

	public function test_source_uses_wp_error_for_editability_failures(): void {
		$this->assertStringContainsString( "'post_not_found'", $this->src );
		$this->assertStringContainsString( "'post_type_forbidden'", $this->src );
		$this->assertStringContainsString( "'insufficient_capability'", $this->src );
	}

	public function test_parse_post_blocks_uses_capability_gate(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*\\\$required,\s*\\\$post_id\s*\)/",
			$this->src
		);
	}

	public function test_parse_post_blocks_delegates_to_core_parser(): void {
		$this->assertStringContainsString( 'parse_blocks( (string) $post->post_content )', $this->src );
	}

	public function test_move_returns_descendant_destination_error(): void {
		$this->assertStringContainsString( "'descendant_destination'", $this->src );
	}

	public function test_validate_attributes_soft_fails_when_type_unregistered(): void {
		$result = Block_Tree::validate_attributes_against_schema( 'nonexistent/block-type', array( 'foo' => 'bar' ) );
		$this->assertTrue( $result );
	}
}
