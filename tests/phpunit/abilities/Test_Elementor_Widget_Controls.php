<?php
/**
 * Feature 067 — Widget_Controls utility tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Elementor\Widget_Controls;
use WP_UnitTestCase;

class Test_Elementor_Widget_Controls extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$this->src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Elementor/Widget_Controls.php'
		);
	}

	public function test_summarize_returns_expected_shape(): void {
		$controls = array(
			'title'   => array( 'label' => 'Title', 'type' => 'text', 'section' => 'content', 'default' => 'Hello' ),
			'align'   => array( 'label' => 'Alignment', 'type' => 'select', 'section' => 'content' ),
			'color'   => array( 'label' => 'Color', 'type' => 'color', 'section' => 'style', 'description' => 'Text color' ),
		);
		$summary = Widget_Controls::summarize( $controls );
		$this->assertCount( 3, $summary );
		$this->assertSame( 'title', $summary[0]['name'] );
		$this->assertSame( 'text', $summary[0]['type'] );
		$this->assertSame( 'Hello', $summary[0]['default'] );
		$this->assertSame( 'Text color', $summary[2]['description'] );
	}

	public function test_summarize_filters_by_search(): void {
		$controls = array(
			'title'      => array( 'label' => 'Title', 'type' => 'text', 'section' => 'content' ),
			'text_color' => array( 'label' => 'Text Color', 'type' => 'color', 'section' => 'style' ),
			'bg_color'   => array( 'label' => 'Background', 'type' => 'color', 'section' => 'style' ),
		);
		$only_color = Widget_Controls::summarize( $controls, 'color' );
		$this->assertCount( 2, $only_color );
	}

	public function test_summarize_returns_empty_when_search_matches_nothing(): void {
		$controls = array( 'title' => array( 'label' => 'Title', 'type' => 'text', 'section' => 'content' ) );
		$this->assertSame( array(), Widget_Controls::summarize( $controls, 'zzz-no-such-thing' ) );
	}

	public function test_source_guards_elementor_presence(): void {
		$this->assertStringContainsString( "class_exists( '\\Elementor\\Plugin' )", $this->src );
	}

	public function test_source_uses_widgets_manager(): void {
		$this->assertStringContainsString( '$widgets_manager->get_widget_types(', $this->src );
	}
}
