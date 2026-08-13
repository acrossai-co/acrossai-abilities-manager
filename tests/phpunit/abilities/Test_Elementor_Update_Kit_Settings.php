<?php
/** Feature 067 — Update_Kit_Settings tests. */
namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;
use WP_UnitTestCase;

class Test_Elementor_Update_Kit_Settings extends WP_UnitTestCase {
	private string $src = '';
	protected function setUp(): void { parent::setUp(); $this->src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Update_Kit_Settings.php' ); }
	public function test_extends(): void { $this->assertStringContainsString( 'extends Ability_Definition', $this->src ); }
	public function test_slug(): void { $this->assertStringContainsString( "'acrossai/elementor-update-kit-settings'", $this->src ); }
	public function test_supports_force_replace(): void { $this->assertStringContainsString( "'force_replace'", $this->src ); }
	public function test_invalidates_cache_site_scope(): void { $this->assertStringContainsString( "Document_Repository::invalidate_cache( \$kit_id, 'site' )", $this->src ); }
	public function test_writes_page_settings(): void { $this->assertStringContainsString( "update_post_meta( \$kit_id, '_elementor_page_settings'", $this->src ); }
}
