<?php
/**
 * Feature 069 — architectural invariants across the whole Rank Math suite.
 *
 * These sweep every file in includes/Abilities/RankMath/ and
 * includes/Abilities/Utilities/RankMath/, so they cover each new ability
 * automatically as it lands rather than needing a per-file assertion.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.28
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Rank_Math_Architecture extends WP_UnitTestCase {

	/** Files in the abilities directory that are not themselves abilities. */
	private const NON_ABILITY_FILES = array(
		'Category_Registrar.php',
		'Base_Rank_Math_Ability.php',
		'Base_Settings_Write_Ability.php',
	);

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/RankMath/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/RankMath/';
	}

	/**
	 * @return string[] Absolute paths.
	 */
	private static function ability_files(): array {
		$files = glob( self::abilities_dir() . '*.php' );
		$files = is_array( $files ) ? $files : array();
		return array_values(
			array_filter(
				$files,
				static fn( string $f ): bool => ! in_array( basename( $f ), self::NON_ABILITY_FILES, true )
			)
		);
	}

	/**
	 * Strip comments and our own namespace segments, so only real third-party
	 * symbol references remain.
	 */
	private static function code_only( string $src ): string {
		$stripped = '';
		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$stripped .= is_array( $token ) ? $token[1] : $token;
		}
		return str_replace(
			array( 'Abilities\\Utilities\\RankMath', 'Abilities\\RankMath' ),
			'',
			$stripped
		);
	}

	public function test_there_is_at_least_one_ability(): void {
		$this->assertNotEmpty( self::ability_files() );
	}

	/**
	 * FR-015 — all third-party access is confined to Utilities/RankMath. This is
	 * what gives one place to absorb a Rank Math API change, one place for a
	 * PHPStan ignore, and one place to test the bridge.
	 *
	 * Comments are stripped first: a docblock may legitimately name a symbol while
	 * explaining why it must not be called here.
	 */
	public function test_no_ability_class_references_a_rank_math_symbol(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );
			$this->assertStringNotContainsString(
				'RankMath',
				$code,
				basename( $file ) . ' names a \\RankMath\\* symbol in code; move it into Utilities/RankMath.'
			);
		}
	}

	/**
	 * Every ability must extend the base, which is the sole assembler of
	 * ability() and sole enforcer of the guard ordering.
	 */
	public function test_every_ability_extends_a_rank_math_base(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );
			$this->assertMatchesRegularExpression(
				'/extends (Base_Rank_Math_Ability|Base_Settings_Write_Ability)\b/',
				$src,
				basename( $file ) . ' must extend a Rank Math ability base.'
			);
		}
	}

	/**
	 * No ability may override the two methods that carry the shared contract.
	 */
	public function test_no_ability_overrides_the_contract_methods(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );
			$this->assertStringNotContainsString( 'function ability()', $src, basename( $file ) . ' must not override ability().' );
			$this->assertStringNotContainsString( 'function execute(', $src, basename( $file ) . ' must not override execute().' );
		}
	}

	/**
	 * FR-009 — destructive and requires_confirmation() must agree. One without
	 * the other is the likely bug: a destructive ability with no confirm gate, or
	 * a confirm gate on something the annotations call safe.
	 *
	 * Note this rule has NO exception for credit-spending abilities. Spending an
	 * unrecoverable paid balance destroys no data, but the destructive annotation
	 * exists to warn clients about irreversibility, which unrecoverable spend is.
	 * One machine-checkable rule beats an exception every client must reason about.
	 */
	public function test_destructive_and_confirmation_agree(): void {
		foreach ( self::ability_files() as $file ) {
			$src         = (string) file_get_contents( $file );
			$destructive = (bool) preg_match( "/'destructive'\s*=>\s*true/", $src );
			$confirms    = (bool) preg_match( '/function requires_confirmation\(\)\s*:\s*bool\s*\{\s*return true;/s', $src );

			$this->assertSame(
				$destructive,
				$confirms,
				basename( $file ) . ': destructive=' . var_export( $destructive, true )
					. ' but requires_confirmation=' . var_export( $confirms, true )
					. '. FR-009 requires both or neither.'
			);
		}
	}

	/**
	 * 'confirm' must never be schema-required.
	 *
	 * WP core validates input_schema BEFORE execute() runs, so a required confirm
	 * makes an unconfirmed call fail with a generic ability_invalid_input
	 * ("confirm is a required property") and assert_confirmed() never fires — the
	 * caller never sees confirmation_required or the message naming the flag,
	 * which defeats the entire gate. Found live in Batch 3.
	 */
	public function test_confirm_is_never_schema_required(): void {
		foreach ( self::ability_files() as $file ) {
			$src = self::code_only( (string) file_get_contents( $file ) );
			if ( ! preg_match( '/function required_input\(\): array \{(.*?)\}/s', $src, $m ) ) {
				continue;
			}
			$this->assertStringNotContainsString(
				"'confirm'",
				$m[1],
				basename( $file ) . " lists 'confirm' as schema-required, which suppresses the confirmation_required error."
			);
		}
	}

	/**
	 * The base must strip it defensively too, so no future subclass can
	 * reintroduce the bug.
	 */
	public function test_base_strips_confirm_from_the_required_list(): void {
		$src = (string) file_get_contents( self::abilities_dir() . 'Base_Rank_Math_Ability.php' );
		$this->assertStringContainsString(
			"array_diff( \$required, array( 'confirm' ) )",
			$src
		);
	}

	/**
	 * Every ability must declare the full annotation triple — a missing key would
	 * silently default and misdescribe the ability to clients.
	 */
	public function test_every_ability_declares_the_full_annotation_triple(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			// The three panel writers inherit annotations() from
			// Base_Settings_Write_Ability, which declares the full triple.
			if ( preg_match( '/extends Base_Settings_Write_Ability\b/', $src ) ) {
				continue;
			}

			foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $key ) {
				$this->assertMatchesRegularExpression(
					"/'{$key}'\s*=>\s*(true|false)/",
					$src,
					basename( $file ) . " is missing the '{$key}' annotation."
				);
			}
		}
	}

	/**
	 * Whatever declares annotations() must declare the whole triple — including
	 * the bases, which the sweep above skips for their subclasses.
	 */
	public function test_bases_declare_the_full_annotation_triple(): void {
		$src = (string) file_get_contents( self::abilities_dir() . 'Base_Settings_Write_Ability.php' );
		foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $key ) {
			$this->assertMatchesRegularExpression( "/'{$key}'\s*=>\s*(true|false)/", $src );
		}
	}

	/**
	 * A readonly ability must not be destructive — that combination is incoherent
	 * and would mislead a client into refusing a safe call or allowing a harmful one.
	 */
	public function test_readonly_abilities_are_not_destructive(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );
			if ( ! preg_match( "/'readonly'\s*=>\s*true/", $src ) ) {
				continue;
			}
			$this->assertDoesNotMatchRegularExpression(
				"/'destructive'\s*=>\s*true/",
				$src,
				basename( $file ) . ' is both readonly and destructive.'
			);
		}
	}

	/**
	 * DEC-UTILITY-STATIC-ONLY — helper classes are final, static-only, and never
	 * singletons.
	 */
	public function test_utilities_are_final_and_static_only(): void {
		$files = glob( self::utilities_dir() . '*.php' );
		$this->assertNotEmpty( $files );
		foreach ( (array) $files as $file ) {
			$src  = (string) file_get_contents( $file );
			$name = basename( $file, '.php' );
			$this->assertStringContainsString( "final class {$name}", $src, "{$name} must be final." );
			$this->assertStringContainsString( 'private function __construct()', $src, "{$name} must have a private constructor." );
			$this->assertStringNotContainsString( 'public static function instance()', $src, "{$name} must not be a singleton." );
		}
	}

	/**
	 * Only Settings_Writer may call Rank Math's settings API. Centralising it is
	 * what guarantees every write carries an explicit field-type map.
	 */
	public function test_only_the_writer_calls_save_settings(): void {
		$callers = array();
		foreach ( array_merge( (array) glob( self::abilities_dir() . '*.php' ), (array) glob( self::utilities_dir() . '*.php' ) ) as $file ) {
			// Comment-stripped: docblocks in the registry and the write base
			// legitimately name the call while explaining why they must not make it.
			$code = self::code_only( (string) file_get_contents( $file ) );
			if ( str_contains( $code, 'Option_Center::save_settings' ) ) {
				$callers[] = basename( (string) $file );
			}
		}
		$this->assertSame( array( 'Settings_Writer.php' ), $callers );
	}

	/**
	 * Every ability must belong to a declared sub-group, so the admin Integrations
	 * page groups it rather than dropping it under a bare category.
	 */
	public function test_every_ability_declares_a_sub_group(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );
			// Either declared directly or inherited from Base_Settings_Write_Ability.
			$has = preg_match( "/function sub_group\(\): string/", $src )
				|| preg_match( '/extends Base_Settings_Write_Ability\b/', $src );
			$this->assertTrue( (bool) $has, basename( $file ) . ' must declare a sub_group.' );
		}
	}
}
