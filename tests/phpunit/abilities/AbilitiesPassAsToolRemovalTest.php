<?php
/**
 * SEC-035-001 — Asserts that pass_as_tool is silently ignored at the Sanitizer layer.
 *
 * Pins the Feature 035 contract: the sanitizer's $tri_state_fields no longer lists
 * pass_as_tool, so a REST request that supplies the obsolete key has it dropped before
 * reaching the DB layer. No validation error is returned, the field is not persisted, and
 * downstream consumers never see it.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Abilities;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Sanitizer;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

class AbilitiesPassAsToolRemovalTest extends TestCase {

	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/abilities' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	public function test_create_request_drops_pass_as_tool(): void {
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request(
			$this->make_request(
				array(
					'ability_slug' => 'test/slug',
					'pass_as_tool' => true,
				)
			)
		);
		$this->assertArrayNotHasKey( 'pass_as_tool', $fields );
	}

	public function test_update_request_drops_pass_as_tool(): void {
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_update_request(
			$this->make_request( array( 'pass_as_tool' => true ) )
		);
		$this->assertArrayNotHasKey( 'pass_as_tool', $fields );
	}

	public function test_create_request_drops_pass_as_tool_when_null(): void {
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request(
			$this->make_request(
				array(
					'ability_slug' => 'test/slug',
					'pass_as_tool' => null,
				)
			)
		);
		$this->assertArrayNotHasKey( 'pass_as_tool', $fields );
	}

	public function test_create_request_drops_pass_as_tool_when_malformed(): void {
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request(
			$this->make_request(
				array(
					'ability_slug' => 'test/slug',
					'pass_as_tool' => array( 'inject' ),
				)
			)
		);
		$this->assertArrayNotHasKey( 'pass_as_tool', $fields );
	}
}
