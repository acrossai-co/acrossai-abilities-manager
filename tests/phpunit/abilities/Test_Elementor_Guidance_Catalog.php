<?php
/**
 * Feature 067 — Guidance_Catalog utility tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Elementor\Guidance_Catalog;
use WP_UnitTestCase;

class Test_Elementor_Guidance_Catalog extends WP_UnitTestCase {

	public function test_seed_catalog_contains_expected_widgets(): void {
		$catalog = Guidance_Catalog::widget_catalog();
		$this->assertNotEmpty( $catalog );
		$names = array_column( $catalog, 'name' );
		foreach ( array( 'heading', 'text-editor', 'image', 'button', 'nav-menu', 'posts' ) as $expected ) {
			$this->assertContains( $expected, $names, "Missing widget: {$expected}" );
		}
	}

	public function test_category_filter(): void {
		$basic = Guidance_Catalog::widget_catalog( 'basic' );
		foreach ( $basic as $widget ) {
			$this->assertSame( 'basic', $widget['category'] );
		}
		$pro = Guidance_Catalog::widget_catalog( 'pro' );
		foreach ( $pro as $widget ) {
			$this->assertSame( 'pro', $widget['category'] );
		}
	}

	public function test_pattern_guidance_returns_shape(): void {
		$guidance = Guidance_Catalog::pattern_guidance();
		$this->assertArrayHasKey( 'source_policy', $guidance );
		$this->assertArrayHasKey( 'guidance_basis', $guidance );
		$this->assertArrayHasKey( 'topics', $guidance );
		$this->assertArrayHasKey( 'widgets', $guidance['topics'] );
		$this->assertArrayHasKey( 'patterns', $guidance['topics'] );
		$this->assertArrayHasKey( 'layouts', $guidance['topics'] );
	}

	public function test_pattern_guidance_with_topic_filter(): void {
		$widgets_topic = Guidance_Catalog::pattern_guidance( 'widgets' );
		$this->assertArrayHasKey( 'topic', $widgets_topic );
		$this->assertSame( 'widgets', $widgets_topic['topic'] );
		$this->assertArrayHasKey( 'guidance', $widgets_topic );
	}

	public function test_categories_constant(): void {
		$this->assertSame( array( 'basic', 'pro', 'theme', 'woocommerce' ), Guidance_Catalog::CATEGORIES );
	}
}
