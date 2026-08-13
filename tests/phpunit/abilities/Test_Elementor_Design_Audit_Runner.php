<?php
/**
 * Feature 067 — Design_Audit_Runner utility tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Elementor\Design_Audit_Runner;
use WP_UnitTestCase;

class Test_Elementor_Design_Audit_Runner extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset the registry to a known state by re-registering fresh audits.
		$reflection = new \ReflectionClass( Design_Audit_Runner::class );
		$prop       = $reflection->getProperty( 'audits' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );
	}

	public function test_register_and_list(): void {
		Design_Audit_Runner::register_audit(
			'test-audit',
			static fn( $post_id ) => array( 'findings' => array(), 'recommendations' => array(), 'score' => 100 )
		);
		$this->assertContains( 'test-audit', Design_Audit_Runner::list_audits() );
	}

	public function test_run_audit_returns_audit_key(): void {
		Design_Audit_Runner::register_audit(
			'sample',
			static fn() => array( 'findings' => array( array( 'message' => 'ok' ) ), 'score' => 42 )
		);
		$result = Design_Audit_Runner::run_audit( 'sample', 123 );
		$this->assertSame( 'sample', $result['audit'] );
		$this->assertSame( 42, $result['score'] );
	}

	public function test_run_audit_returns_error_for_unregistered(): void {
		$result = Design_Audit_Runner::run_audit( 'nonexistent', 1 );
		$this->assertSame( 'audit_not_registered', $result['error'] );
	}

	public function test_run_audit_catches_throwable(): void {
		Design_Audit_Runner::register_audit(
			'thrower',
			static function () {
				throw new \RuntimeException( 'boom' );
			}
		);
		$result = Design_Audit_Runner::run_audit( 'thrower', 1 );
		$this->assertSame( 'boom', $result['error'] );
	}

	public function test_run_all_aggregates_findings_and_scores(): void {
		Design_Audit_Runner::register_audit(
			'a',
			static fn() => array(
				'findings'        => array( array( 'message' => 'a-issue' ) ),
				'recommendations' => array( array( 'suggestion' => 'a-fix' ) ),
				'score'           => 80,
			)
		);
		Design_Audit_Runner::register_audit(
			'b',
			static fn() => array(
				'findings'        => array( array( 'message' => 'b-issue' ) ),
				'recommendations' => array(),
				'score'           => 60,
			)
		);
		$aggregate = Design_Audit_Runner::run_all( 42 );
		$this->assertSame( 42, $aggregate['post_id'] );
		$this->assertSame( 2, $aggregate['audit_count'] );
		$this->assertCount( 2, $aggregate['findings'] );
		$this->assertCount( 1, $aggregate['recommendations'] );
		$this->assertSame( 70.0, (float) $aggregate['score'] );
	}
}
