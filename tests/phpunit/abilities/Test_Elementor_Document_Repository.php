<?php
/**
 * Feature 067 — tests for Document_Repository utility.
 *
 * Mix of behavioural (pure static tree ops on plain arrays) and
 * source-inspection tests (IO paths that require WP fixtures).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Elementor\Document_Repository;
use WP_UnitTestCase;

class Test_Elementor_Document_Repository extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Elementor/Document_Repository.php'
		);
	}

	// Behavioural tests on pure-static tree helpers.
	private function fixture(): array {
		$leaf = static function ( string $id, string $widget ): array {
			return array(
				'id'        => $id,
				'elType'    => 'widget',
				'widgetType' => $widget,
				'settings'  => array(),
				'elements'  => array(),
			);
		};
		return array(
			array(
				'id'       => 'aaaaaaa',
				'elType'   => 'container',
				'settings' => array(),
				'elements' => array(
					$leaf( 'bbbbbbb', 'heading' ),
					$leaf( 'ccccccc', 'paragraph' ),
				),
			),
			$leaf( 'ddddddd', 'button' ),
		);
	}

	public function test_is_valid_element_id_accepts_7_hex(): void {
		$this->assertTrue( Document_Repository::is_valid_element_id( 'a1b2c3d' ) );
		$this->assertTrue( Document_Repository::is_valid_element_id( 'aaaaaaa' ) );
		$this->assertFalse( Document_Repository::is_valid_element_id( '' ) );
		$this->assertFalse( Document_Repository::is_valid_element_id( 'ABCDEF1' ) );
		$this->assertFalse( Document_Repository::is_valid_element_id( 'g1b2c3d' ) );
		$this->assertFalse( Document_Repository::is_valid_element_id( 'a1b2c3' ) );
	}

	public function test_generate_element_id_returns_valid_id(): void {
		$id = Document_Repository::generate_element_id();
		$this->assertTrue( Document_Repository::is_valid_element_id( $id ) );
	}

	public function test_find_element_by_id_returns_element_and_parent_path(): void {
		$found = Document_Repository::find_element_by_id( $this->fixture(), 'bbbbbbb' );
		$this->assertIsArray( $found );
		$this->assertSame( 'bbbbbbb', $found['element']['id'] );
		$this->assertSame( array( 'aaaaaaa' ), $found['path'] );
	}

	public function test_find_element_by_id_returns_null_for_unknown(): void {
		$this->assertNull( Document_Repository::find_element_by_id( $this->fixture(), 'zzzzzzz' ) );
	}

	public function test_remove_element_by_id_removes_and_returns(): void {
		$elements = $this->fixture();
		$removed  = Document_Repository::remove_element_by_id( $elements, 'bbbbbbb' );
		$this->assertIsArray( $removed );
		$this->assertSame( 'bbbbbbb', $removed['id'] );
		$this->assertCount( 1, $elements[0]['elements'] );
	}

	public function test_insert_element_at_root_appends(): void {
		$elements = $this->fixture();
		$new      = array( 'id' => 'eeeeeee', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array(), 'elements' => array() );
		$this->assertTrue( Document_Repository::insert_element( $elements, null, 999, $new ) );
		$this->assertCount( 3, $elements );
		$this->assertSame( 'eeeeeee', $elements[2]['id'] );
	}

	public function test_insert_element_inside_parent(): void {
		$elements = $this->fixture();
		$new      = array( 'id' => 'fffffff', 'elType' => 'widget', 'widgetType' => 'divider', 'settings' => array(), 'elements' => array() );
		$this->assertTrue( Document_Repository::insert_element( $elements, 'aaaaaaa', 0, $new ) );
		$this->assertSame( 'fffffff', $elements[0]['elements'][0]['id'] );
	}

	public function test_replace_element_by_id(): void {
		$elements    = $this->fixture();
		$replacement = array( 'id' => 'bbbbbbb', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Updated' ), 'elements' => array() );
		$this->assertTrue( Document_Repository::replace_element_by_id( $elements, 'bbbbbbb', $replacement ) );
		$this->assertSame( 'Updated', $elements[0]['elements'][0]['settings']['title'] );
	}

	public function test_reorder_children_root(): void {
		$elements = $this->fixture();
		$result   = Document_Repository::reorder_children( $elements, null, array( 'ddddddd', 'aaaaaaa' ) );
		$this->assertTrue( $result );
		$this->assertSame( 'ddddddd', $elements[0]['id'] );
		$this->assertSame( 'aaaaaaa', $elements[1]['id'] );
	}

	public function test_reassign_subtree_ids_generates_fresh_ids(): void {
		$element = $this->fixture()[0];
		$clone   = Document_Repository::reassign_subtree_ids( $element );
		$this->assertNotSame( 'aaaaaaa', $clone['id'] );
		$this->assertNotSame( 'bbbbbbb', $clone['elements'][0]['id'] );
		$this->assertNotSame( 'ccccccc', $clone['elements'][1]['id'] );
		$this->assertTrue( Document_Repository::is_valid_element_id( $clone['id'] ) );
	}

	public function test_path_starts_with(): void {
		$this->assertTrue( Document_Repository::path_starts_with( array( 'a', 'b', 'c' ), array( 'a', 'b' ) ) );
		$this->assertTrue( Document_Repository::path_starts_with( array( 'a' ), array( 'a' ) ) );
		$this->assertFalse( Document_Repository::path_starts_with( array( 'a', 'b' ), array( 'a', 'b', 'c' ) ) );
		$this->assertFalse( Document_Repository::path_starts_with( array( 'x' ), array( 'a' ) ) );
	}

	public function test_is_document_populated(): void {
		$this->assertFalse( Document_Repository::is_document_populated( array() ) );
		$this->assertTrue( Document_Repository::is_document_populated( $this->fixture() ) );
	}

	public function test_decode_data_handles_empty_and_valid(): void {
		$this->assertSame( array(), Document_Repository::decode_data( '' ) );
		$decoded = Document_Repository::decode_data( '[{"id":"abc1234","elType":"widget"}]' );
		$this->assertCount( 1, $decoded );
		$this->assertSame( 'abc1234', $decoded[0]['id'] );
	}

	public function test_decode_data_populates_error_on_invalid(): void {
		$err = null;
		$decoded = Document_Repository::decode_data( 'not json', $err );
		$this->assertSame( array(), $decoded );
		$this->assertNotNull( $err );
	}

	// Source-inspection: IO paths.
	public function test_source_declares_forbidden_post_types(): void {
		foreach ( array( 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request' ) as $forbidden ) {
			$this->assertStringContainsString( "'{$forbidden}'", $this->src );
		}
	}

	public function test_save_data_uses_wp_slash(): void {
		$this->assertStringContainsString( "update_post_meta( \$post_id, '_elementor_data', wp_slash( \$json ) )", $this->src );
	}

	public function test_invalidate_cache_clears_files_manager_on_site_scope(): void {
		$this->assertStringContainsString( '$instance->files_manager->clear_cache()', $this->src );
	}

	public function test_assert_post_type_editable_returns_error_codes(): void {
		$this->assertStringContainsString( "'post_not_found'", $this->src );
		$this->assertStringContainsString( "'post_type_forbidden'", $this->src );
	}

	public function test_assert_elementor_available_returns_wp_error(): void {
		$this->assertStringContainsString( "'elementor_missing'", $this->src );
	}

	public function test_assert_elementor_pro_available_returns_wp_error(): void {
		$this->assertStringContainsString( "'elementor_pro_missing'", $this->src );
	}
}
