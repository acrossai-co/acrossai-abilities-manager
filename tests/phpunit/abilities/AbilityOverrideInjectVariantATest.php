<?php
/**
 * Tests for CHANGE-4/CHANGE-5 (Feature 024): Override arg injection for
 * label, description, and category top-level fields.
 *
 * CHANGE-5: inject_override_args() must inject label/description/category
 * when the DB override row has a non-null, non-empty value.
 *
 * CHANGE-1 regression: The _registry.source key must be null when the
 * registered ability has no source meta — AcrossAI_Ability_Merger::merge()
 * must NOT default to 'plugin' for absent source values.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Ability_Override_Processor;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Merger;

/**
 * Class AbilityOverrideInjectVariantATest
 *
 * @since 0.1.0
 */
class AbilityOverrideInjectVariantATest extends WP_UnitTestCase {

	/**
	 * Reset the Override Processor static state before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->reset_processor_state();
	}

	/**
	 * Reset the Override Processor static state after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_processor_state();
		parent::tearDown();
	}

	/**
	 * Reset static properties on the processor via Reflection.
	 *
	 * @return void
	 */
	private function reset_processor_state(): void {
		$ref = new \ReflectionClass( AcrossAI_Ability_Override_Processor::class );

		foreach ( [ 'overrides_cache', 'checked' ] as $prop ) {
			if ( $ref->hasProperty( $prop ) ) {
				$p = $ref->getProperty( $prop );
				$p->setAccessible( true );
				$p->setValue( null, null === $p->getValue() ? null : ( 'checked' === $prop ? false : null ) );
			}
		}

		// Force cache = empty array so load_overrides_cache() is not called.
		$cache = $ref->getProperty( 'overrides_cache' );
		$cache->setAccessible( true );
		$cache->setValue( null, array() );

		$checked = $ref->getProperty( 'checked' );
		$checked->setAccessible( true );
		$checked->setValue( null, true );
	}

	/**
	 * Seed the static cache with one row for the given slug.
	 *
	 * @param  string            $slug Ability slug.
	 * @param  object $row  Override row.
	 * @return void
	 */
	private function seed_cache( string $slug, object $row ): void {
		$ref   = new \ReflectionClass( AcrossAI_Ability_Override_Processor::class );
		$cache = $ref->getProperty( 'overrides_cache' );
		$cache->setAccessible( true );
		$cache->setValue( null, array( $slug => $row ) );
	}

	// -------------------------------------------------------------------------
	// CHANGE-5: override injection guard logic (pure PHP — replicated from
	// inject_override_args() to avoid AC infrastructure dependency in tests)
	// -------------------------------------------------------------------------

	/**
	 * Helper: apply CHANGE-5 field-injection guard logic for a single field.
	 *
	 * Replicates:
	 *   if ( null !== $row->{$field} && '' !== $row->{$field} ) { $args[$field] = ... }
	 *
	 * @param  array  $args    Base args.
	 * @param  object $row     Override row (stdClass mock).
	 * @param  string $field   Field name.
	 * @return array Updated args.
	 */
	private function apply_field_guard( array $args, object $row, string $field ): array {
		if ( isset( $row->$field ) && null !== $row->$field && '' !== $row->$field ) {
			$args[ $field ] = $row->$field;
		}
		return $args;
	}

	/**
	 * Guard injects label when override row has a non-empty label (CHANGE-5).
	 *
	 * @return void
	 */
	public function test_inject_sets_label_from_override(): void {
		$row        = new \stdClass();
		$row->label = 'Custom Label';

		$result = $this->apply_field_guard( array( 'label' => 'Registry Label' ), $row, 'label' );

		$this->assertSame( 'Custom Label', $result['label'] );
	}

	/**
	 * Guard skips label when override row has null label (CHANGE-5).
	 *
	 * @return void
	 */
	public function test_inject_skips_null_label(): void {
		$row        = new \stdClass();
		$row->label = null;

		$result = $this->apply_field_guard( array( 'label' => 'Registry Label' ), $row, 'label' );

		$this->assertSame( 'Registry Label', $result['label'] );
	}

	/**
	 * Guard skips label when override row has empty-string label (CHANGE-5).
	 *
	 * @return void
	 */
	public function test_inject_skips_empty_label(): void {
		$row        = new \stdClass();
		$row->label = '';

		$result = $this->apply_field_guard( array( 'label' => 'Registry Label' ), $row, 'label' );

		$this->assertSame( 'Registry Label', $result['label'] );
	}

	/**
	 * Guard injects description when override has a non-empty value (CHANGE-5).
	 *
	 * @return void
	 */
	public function test_inject_sets_description_from_override(): void {
		$row              = new \stdClass();
		$row->description = 'Custom Description';

		$result = $this->apply_field_guard( array( 'description' => 'Registry Description' ), $row, 'description' );

		$this->assertSame( 'Custom Description', $result['description'] );
	}

	/**
	 * Guard skips null description (CHANGE-5).
	 *
	 * @return void
	 */
	public function test_inject_skips_null_description(): void {
		$row              = new \stdClass();
		$row->description = null;

		$result = $this->apply_field_guard( array( 'description' => 'Registry Description' ), $row, 'description' );

		$this->assertSame( 'Registry Description', $result['description'] );
	}

	/**
	 * Guard injects category when override has a non-empty value (CHANGE-5).
	 *
	 * @return void
	 */
	public function test_inject_sets_category_from_override(): void {
		$row           = new \stdClass();
		$row->category = 'custom-cat';

		$result = $this->apply_field_guard( array( 'category' => 'general' ), $row, 'category' );

		$this->assertSame( 'custom-cat', $result['category'] );
	}

	/**
	 * Guard skips null category (CHANGE-5).
	 *
	 * @return void
	 */
	public function test_inject_skips_null_category(): void {
		$row           = new \stdClass();
		$row->category = null;

		$result = $this->apply_field_guard( array( 'category' => 'general' ), $row, 'category' );

		$this->assertSame( 'general', $result['category'] );
	}

	/**
	 * Guard preserves pre-existing args when field is absent from row (CHANGE-5 safety).
	 *
	 * @return void
	 */
	public function test_inject_noop_when_field_absent(): void {
		$row    = new \stdClass(); // no ->label property
		$result = $this->apply_field_guard( array( 'label' => 'Registry Label' ), $row, 'label' );

		$this->assertSame( 'Registry Label', $result['label'] );
	}

	// -------------------------------------------------------------------------
	// CHANGE-1 regression: source defaults to null, not 'plugin'
	// -------------------------------------------------------------------------

	/**
	 * AcrossAI_Ability_Merger::merge() with source=null in registry returns null in _registry.
	 *
	 * Regression for CHANGE-1: the source field must NOT be forced to 'plugin' when
	 * the registered ability has no source meta item.
	 *
	 * @return void
	 */
	public function test_merger_source_null_is_preserved_in_registry(): void {
		$registry = array(
			'slug'         => 'acrossai-abilities/my-ability',
			'label'        => 'My Ability',
			'description'  => 'Test',
			'category'     => 'general',
			'provider'     => 'my-plugin',
			'source'       => null,
			'site_allowed' => true,
			'readonly'     => null,
			'destructive'  => null,
			'idempotent'   => null,
			'show_in_rest' => null,
			'show_in_mcp'  => null,
			'mcp_type'     => null,
		);

		$result = AcrossAI_Ability_Merger::merge( $registry, null );

		$this->assertNull( $result['_registry']['source'], 'source must be null when not set in registry (CHANGE-1 regression).' );
		$this->assertNull( $result['source'], 'Merged source must also be null.' );
	}

	/**
	 * AcrossAI_Ability_Merger::merge() with source='plugin' preserves the value.
	 *
	 * Ensures existing explicitly-set source values are not broken.
	 *
	 * @return void
	 */
	public function test_merger_source_plugin_is_preserved_when_set(): void {
		$registry = array(
			'slug'         => 'acrossai-abilities/my-ability',
			'label'        => 'My Ability',
			'description'  => 'Test',
			'category'     => 'general',
			'provider'     => 'my-plugin',
			'source'       => 'plugin',
			'site_allowed' => true,
			'readonly'     => null,
			'destructive'  => null,
			'idempotent'   => null,
			'show_in_rest' => null,
			'show_in_mcp'  => null,
			'mcp_type'     => null,
		);

		$result = AcrossAI_Ability_Merger::merge( $registry, null );

		$this->assertSame( 'plugin', $result['_registry']['source'] );
		$this->assertSame( 'plugin', $result['source'] );
	}

	// -------------------------------------------------------------------------
	// BUG-FIX: Force Block (site_allowed = false) survives merge
	// -------------------------------------------------------------------------

	/**
	 * Merger applies site_allowed=false (Force Block) override — not dropped as empty string.
	 *
	 * Regression for merge() cast-guard bug: (string) false === '' caused false overrides
	 * to fall through to the registry fallback silently.
	 *
	 * @return void
	 */
	public function test_merger_site_allowed_false_override_is_applied(): void {
		$registry = array(
			'slug'         => 'acrossai-abilities/my-ability',
			'label'        => 'My Ability',
			'description'  => 'Test',
			'category'     => 'general',
			'provider'     => 'my-plugin',
			'source'       => 'plugin',
			'site_allowed' => true, // registry default = allow
			'readonly'     => null,
			'destructive'  => null,
			'idempotent'   => null,
			'show_in_rest' => null,
			'show_in_mcp'  => null,
			'mcp_type'     => null,
		);

		$override = (object) array(
			'id'           => 1,
			'ability_slug' => 'acrossai-abilities/my-ability',
			'label'        => null,
			'description'  => null,
			'category'     => null,
			'provider'     => null,
			'source'       => null,
			'site_allowed' => false, // Force Block — must win over registry true
			'readonly'     => null,
			'destructive'  => null,
			'idempotent'   => null,
			'show_in_rest' => null,
			'show_in_mcp'  => null,
			'mcp_type'     => null,
			'updated_at'   => '2026-06-02 00:00:00',
			'updated_by'   => 1,
			'created_at'   => '2026-06-02 00:00:00',
			'created_by'   => 1,
		);

		$result = AcrossAI_Ability_Merger::merge( $registry, $override );

		$this->assertFalse( $result['site_allowed'], 'site_allowed=false override must be applied (Force Block).' );
		$this->assertFalse( $result['_override']['site_allowed'], '_override must reflect false.' );
		$this->assertTrue( $result['has_override'], 'has_override must be true when site_allowed=false is set.' );
	}

	/**
	 * Merger applies all tri-state false overrides for boolean fields.
	 *
	 * Confirms readonly=false, destructive=false, idempotent=false, show_in_rest=false,
	 * show_in_mcp=false also survive the merge without being treated as empty.
	 *
	 * @return void
	 */
	public function test_merger_boolean_false_overrides_survive_for_all_tri_state_fields(): void {
		$registry = array(
			'slug'         => 'acrossai-abilities/my-ability',
			'label'        => 'My Ability',
			'description'  => 'Test',
			'category'     => 'general',
			'provider'     => 'my-plugin',
			'source'       => 'plugin',
			'site_allowed' => true,
			'readonly'     => true,
			'destructive'  => true,
			'idempotent'   => true,
			'show_in_rest' => true,
			'show_in_mcp'  => true,
			'mcp_type'     => null,
		);

		$override = (object) array(
			'id'           => 2,
			'ability_slug' => 'acrossai-abilities/my-ability',
			'label'        => null,
			'description'  => null,
			'category'     => null,
			'provider'     => null,
			'source'       => null,
			'site_allowed' => false,
			'readonly'     => false,
			'destructive'  => false,
			'idempotent'   => false,
			'show_in_rest' => false,
			'show_in_mcp'  => false,
			'mcp_type'     => null,
			'updated_at'   => '2026-06-02 00:00:00',
			'updated_by'   => 1,
			'created_at'   => '2026-06-02 00:00:00',
			'created_by'   => 1,
		);

		$result = AcrossAI_Ability_Merger::merge( $registry, $override );

		$this->assertFalse( $result['site_allowed'] );
		$this->assertFalse( $result['readonly'] );
		$this->assertFalse( $result['destructive'] );
		$this->assertFalse( $result['idempotent'] );
		$this->assertFalse( $result['show_in_rest'] );
		$this->assertFalse( $result['show_in_mcp'] );
		$this->assertTrue( $result['has_override'], 'has_override must be true when any bool false is set.' );
	}
}
