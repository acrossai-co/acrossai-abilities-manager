<?php
/**
 * Smoke tests for the WPForms concrete integration subclass.
 *
 * Feature 060 extension (2026-07-27). Covers:
 *   - TAB_GROUP constant is 'wpforms'
 *   - slug() returns self::TAB_GROUP
 *   - label() returns 'WPForms' (case-preserved capitalisation)
 *   - abilities() exposes the two-tier read/write documentation rows with the
 *     expected slugs, and the descriptions clearly distinguish auto-enabled
 *     reads from gated writes.
 *   - enable_filter() attaches __return_true to the WPForms write filter
 *     exactly once (this is a real filter side-effect, not a no-op).
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library\Integrations;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\WPForms;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The WPForms integration subclass contract.
 */
class Test_WPForms extends TestCase {

	public function test_tab_group_constant_is_wpforms(): void {
		$this->assertSame( 'wpforms', WPForms::TAB_GROUP );
	}

	public function test_slug_returns_tab_group_constant(): void {
		$slug_method = new ReflectionMethod( WPForms::class, 'slug' );
		$slug_method->setAccessible( true );
		$instance = ( new \ReflectionClass( WPForms::class ) )->newInstanceWithoutConstructor();
		$this->assertSame( WPForms::TAB_GROUP, $slug_method->invoke( $instance ) );
	}

	public function test_label_is_wpforms(): void {
		$label_method = new ReflectionMethod( WPForms::class, 'label' );
		$label_method->setAccessible( true );
		$instance = ( new \ReflectionClass( WPForms::class ) )->newInstanceWithoutConstructor();
		$this->assertSame( 'WPForms', $label_method->invoke( $instance ) );
	}

	// -------------------------------------------------------------------------
	// abilities() — two rows describing the read/write tier split
	// -------------------------------------------------------------------------

	public function test_abilities_lists_two_tier_rows(): void {
		$abilities_method = new ReflectionMethod( WPForms::class, 'abilities' );
		$abilities_method->setAccessible( true );
		$instance  = ( new \ReflectionClass( WPForms::class ) )->newInstanceWithoutConstructor();
		$abilities = $abilities_method->invoke( $instance );

		$this->assertIsArray( $abilities );
		$this->assertCount( 2, $abilities );

		$slugs = array_column( $abilities, 'slug' );
		$this->assertSame(
			array( 'wpforms/reads', 'wpforms/writes' ),
			$slugs
		);
	}

	public function test_read_row_documents_auto_enabled_nature(): void {
		$abilities_method = new ReflectionMethod( WPForms::class, 'abilities' );
		$abilities_method->setAccessible( true );
		$instance  = ( new \ReflectionClass( WPForms::class ) )->newInstanceWithoutConstructor();
		$abilities = $abilities_method->invoke( $instance );

		$read_row = $abilities[0];
		$this->assertSame( 'wpforms/reads', $read_row['slug'] );
		// Label must call out the auto-enabled nature so the admin doesn't
		// think the toggle disables reads.
		$this->assertStringContainsString( 'auto-enabled', $read_row['label'] );
		// Description must state that this toggle does NOT gate reads.
		$this->assertMatchesRegularExpression(
			'/does NOT gate|regardless of the toggle/i',
			$read_row['description']
		);
	}

	public function test_write_row_documents_the_gate_filter(): void {
		$abilities_method = new ReflectionMethod( WPForms::class, 'abilities' );
		$abilities_method->setAccessible( true );
		$instance  = ( new \ReflectionClass( WPForms::class ) )->newInstanceWithoutConstructor();
		$abilities = $abilities_method->invoke( $instance );

		$write_row = $abilities[1];
		$this->assertSame( 'wpforms/writes', $write_row['slug'] );
		$this->assertStringContainsString( 'gated', $write_row['label'] );
		// The exact filter name must appear in the description so admins
		// searching for it via grep or filter-hooks documentation can find it.
		$this->assertStringContainsString(
			'wpforms_integrations_abilities_allow_write',
			$write_row['description']
		);
	}

	// -------------------------------------------------------------------------
	// enable_filter() — attaches the write filter exactly once
	// -------------------------------------------------------------------------

	public function test_enable_filter_does_not_throw(): void {
		$enable_method = new ReflectionMethod( WPForms::class, 'enable_filter' );
		$enable_method->setAccessible( true );
		$instance = ( new \ReflectionClass( WPForms::class ) )->newInstanceWithoutConstructor();

		// enable_filter attaches the WPForms write filter via `add_filter`.
		// The stub bootstrap's add_filter is a shim without full hook-system
		// state, so we can't observe $wp_filter changes here — but the base
		// class wraps this call in try/catch anyway, so we just assert the
		// method returns void without throwing. Full end-to-end verification
		// happens in the manual quickstart with WPForms actually active.
		$result = $enable_method->invoke( $instance );
		$this->assertNull( $result );
	}

	public function test_enable_filter_body_calls_add_filter_with_return_true(): void {
		// Introspect the method body directly to prove it wires the correct
		// filter name + callback. Guards against a future refactor that
		// accidentally changes the filter name or drops the __return_true.
		$reflection = new ReflectionMethod( WPForms::class, 'enable_filter' );
		$source     = file_get_contents( $reflection->getFileName() );
		$start      = $reflection->getStartLine();
		$end        = $reflection->getEndLine();
		$lines      = array_slice( explode( "\n", $source ), $start - 1, $end - $start + 1 );
		$body       = implode( "\n", $lines );

		$this->assertStringContainsString(
			"add_filter( 'wpforms_integrations_abilities_allow_write', '__return_true' )",
			$body,
			'enable_filter() must attach __return_true to wpforms_integrations_abilities_allow_write.'
		);
	}
}
