<?php
/** Feature 067 — normalize heading/body/button typography in a subtree. */
namespace AcrossAI_Abilities_Manager\Includes\Abilities\Elementor;
defined( 'ABSPATH' ) || exit;

class Apply_Text_Hierarchy extends Base_Audit_Ability {
	protected function audit_slug(): string { return 'apply-text-hierarchy'; }
	protected function audit_label(): string { return __( 'Apply Elementor Text Hierarchy', 'acrossai-abilities-manager' ); }
	protected function audit_description(): string { return __( 'Normalise heading/body/button typography in a subtree. Prefers Elementor global typography references over local overrides. Skeleton implementation.', 'acrossai-abilities-manager' ); }
	protected function is_destructive(): bool { return true; }
	protected function analyze( int $post_id, string $subtree_id ): array { return array( 'findings' => array(), 'recommendations' => array( array( 'suggestion' => 'Skeleton — no changes applied.' ) ) ); }
}
