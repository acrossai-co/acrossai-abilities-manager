<?php
/**
 * Feature 058 — one-time slug rename migration.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Abilities/Database
 * @since      0.0.16
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Renames every ability slug from the pre-0.0.16 form
 * `acrossai-abilities-manager/<subject-first>` to the standardized
 * `acrossai/<verb-first>` form (Feature 058). Both the namespace prefix
 * shortens AND the word order flips in one migration. Idempotent: the option
 * flag `acrossai_abilities_slug_rename_058_done` marks completion so repeated
 * activation or admin_init runs no-op.
 *
 * Updates three tables in a single transaction:
 *  1. {prefix}acrossai_abilities.ability_slug        — this plugin's ability rows
 *  2. {prefix}abilities_access_control.`key`         — this plugin's ACL rules
 *                                                      (WHERE namespace = 'acrossai-abilities')
 *  3. {prefix}acrossai_mcp_server_abilities.ability_slug — sibling acrossai-mcp-manager
 *                                                          plugin's exposure rows,
 *                                                          updated defensively only
 *                                                          if the table exists
 */
class AcrossAI_Slug_Rename_Migration_058 {

	/**
	 * Option key used to mark this migration as complete.
	 */
	private const DONE_OPTION = 'acrossai_abilities_slug_rename_058_done';

	/**
	 * Namespace prefix rows carry BEFORE the migration (pre-0.0.16 form).
	 */
	private const OLD_PREFIX = 'acrossai-abilities-manager/';

	/**
	 * Namespace prefix rows carry AFTER the migration (0.0.16 form).
	 */
	private const NEW_PREFIX = 'acrossai/';

	/**
	 * ACL rows are scoped to this namespace value.
	 */
	private const ACL_NAMESPACE = 'acrossai-abilities';

	/**
	 * Run the migration if not already done. Safe to call multiple times.
	 *
	 * @return void
	 */
	public static function maybe_run(): void {
		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}
		self::run();
	}

	/**
	 * Execute the migration inside a transaction and mark it done on success.
	 *
	 * @return void
	 */
	private static function run(): void {
		global $wpdb;

		$map = self::map();

		$abilities_table = $wpdb->prefix . 'acrossai_abilities';
		$acl_table       = $wpdb->prefix . 'abilities_access_control';
		$mcp_table       = $wpdb->prefix . 'acrossai_mcp_server_abilities';

		$mcp_exists = self::table_exists( $mcp_table );

		$wpdb->query( 'START TRANSACTION' );

		foreach ( $map as $old => $new ) {
			$old_full = self::OLD_PREFIX . $old;
			$new_full = self::NEW_PREFIX . $new;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$abilities_table} SET ability_slug = %s WHERE ability_slug = %s",
					$new_full,
					$old_full
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$acl_table} SET `key` = %s WHERE `key` = %s AND `namespace` = %s",
					$new_full,
					$old_full,
					self::ACL_NAMESPACE
				)
			);

			if ( $mcp_exists ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$mcp_table} SET ability_slug = %s WHERE ability_slug = %s",
						$new_full,
						$old_full
					)
				);
			}
			// phpcs:enable
		}

		// Second pass — bulk namespace shortening for the 56 unchanged-suffix
		// slugs (and defensively any renamed row that somehow escaped step 1).
		// Uses MySQL REPLACE() so a single UPDATE per table handles every
		// remaining OLD_PREFIX row.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$abilities_table} SET ability_slug = REPLACE( ability_slug, %s, %s ) WHERE ability_slug LIKE %s",
				self::OLD_PREFIX,
				self::NEW_PREFIX,
				self::OLD_PREFIX . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$acl_table} SET `key` = REPLACE( `key`, %s, %s ) WHERE `key` LIKE %s AND `namespace` = %s",
				self::OLD_PREFIX,
				self::NEW_PREFIX,
				self::OLD_PREFIX . '%',
				self::ACL_NAMESPACE
			)
		);

		if ( $mcp_exists ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$mcp_table} SET ability_slug = REPLACE( ability_slug, %s, %s ) WHERE ability_slug LIKE %s",
					self::OLD_PREFIX,
					self::NEW_PREFIX,
					self::OLD_PREFIX . '%'
				)
			);
		}
		// phpcs:enable

		$wpdb->query( 'COMMIT' );

		update_option( self::DONE_OPTION, gmdate( 'c' ) );
	}

	/**
	 * Check whether a table exists (defensive — the MCP table lives in a sibling
	 * plugin and may be absent).
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		// phpcs:enable
		return (string) $found === $table;
	}

	/**
	 * Old-to-new slug map, sorted longest-first to prevent prefix collisions
	 * during any string-based downstream consumption (the DB layer here uses
	 * exact-match WHERE clauses, so order is not strictly required — but keeping
	 * the ordering matches the code-side rename script's convention).
	 *
	 * @return array<string,string>
	 */
	private static function map(): array {
		return array(
			'admin-menu-get-context'                    => 'get-admin-menu-context',
			'admin-menu-get-navigation-target'          => 'get-admin-menu-navigation-target',
			'admin-menu-list-pages'                     => 'list-admin-menu-pages',
			'admin-menu-list-settings'                  => 'list-admin-settings',
			'admin-menu-refresh-context'                => 'refresh-admin-menu-context',
			'block-info-list'                           => 'list-blocks',
			'block-info-read'                           => 'read-block',
			'block-pattern-create'                      => 'create-block-pattern',
			'block-pattern-delete'                      => 'delete-block-pattern',
			'block-pattern-list'                        => 'list-block-patterns',
			'block-pattern-read'                        => 'read-block-pattern',
			'block-pattern-update'                      => 'update-block-pattern',
			'block-style-variations-create'             => 'create-block-style-variation',
			'block-style-variations-delete'             => 'delete-block-style-variation',
			'block-style-variations-list'               => 'list-block-style-variations',
			'block-style-variations-read'               => 'read-block-style-variation',
			'block-style-variations-update'             => 'update-block-style-variation',
			'global-styles-create'                      => 'create-global-style',
			'global-styles-delete'                      => 'delete-global-style',
			'global-styles-list'                        => 'list-global-styles',
			'global-styles-read'                        => 'read-global-style',
			'global-styles-update'                      => 'update-global-style',
			'site-editor-get-context'                   => 'get-site-editor-context',
			'site-editor-refresh-context'               => 'refresh-site-editor-context',
			'site-structure-list-block-areas'           => 'list-block-areas',
			'site-structure-list-reusable-blocks'       => 'list-reusable-blocks',
			'template-create'                           => 'create-block-template',
			'template-delete'                           => 'delete-block-template',
			'template-list'                             => 'list-block-templates',
			'template-read'                             => 'read-block-template',
			'template-update'                           => 'update-block-template',
			'template-part-create'                      => 'create-block-template-part',
			'template-part-delete'                      => 'delete-block-template-part',
			'template-part-list'                        => 'list-block-template-parts',
			'template-part-read'                        => 'read-block-template-part',
			'template-part-update'                      => 'update-block-template-part',
			'theme-json-read'                           => 'read-theme-json',
			'theme-json-update'                         => 'update-theme-json',
			'cache-flush'                               => 'flush-object-cache',
			'rewrite-flush'                             => 'flush-rewrite-rules',
			'transient-flush'                           => 'flush-transients',
			'comments-bulk-update'                      => 'bulk-update-comments',
			'content-autosaves-inspect'                 => 'inspect-post-autosaves',
			'content-update-block'                      => 'update-post-block',
			'get-cpt-items'                             => 'list-cpt-items',
			'get-cpt-item-revisions'                    => 'list-cpt-item-revisions',
			'get-pages'                                 => 'list-pages',
			'get-page-revisions'                        => 'list-page-revisions',
			'get-posts'                                 => 'list-posts',
			'get-post-revisions'                        => 'list-post-revisions',
			'get-post-translations'                     => 'list-post-translations',
			'je-get-options-page'                       => 'get-jet-engine-options-page',
			'je-list-options-pages'                     => 'list-jet-engine-options-pages',
			'je-update-options-page-field'              => 'update-jet-engine-options-page-field',
			'content-audit-internal-links'              => 'audit-internal-links',
			'content-find-internal-links'               => 'find-internal-links',
			'content-find-related'                      => 'find-related-content',
			'content-index-refresh-batch'               => 'refresh-content-index-batch',
			'content-internal-link-policy'              => 'get-internal-link-policy',
			'content-internal-link-suggestion-apply'    => 'apply-internal-link-suggestion',
			'content-internal-link-suggestion-review'   => 'review-internal-link-suggestion',
			'content-internal-link-suggestions-create'  => 'create-internal-link-suggestions',
			'content-internal-link-suggestions-list'    => 'list-internal-link-suggestions',
			'content-search-chunks'                     => 'search-content-chunks',
			'content-search-items'                      => 'search-content-items',
			'wp-core-reinstall'                         => 'reinstall-wp-core',
			'wp-core-rollback'                          => 'rollback-wp-core',
			'wp-core-update'                            => 'update-wp-core',
			'wp-core-update-check'                      => 'check-wp-core-update',
			'cron-create'                               => 'create-cron-job',
			'cron-create-schedule'                      => 'create-cron-schedule',
			'cron-delete'                               => 'delete-cron-job',
			'cron-delete-all'                           => 'delete-cron-jobs-by-hook',
			'cron-delete-schedule'                      => 'delete-cron-schedule',
			'cron-exists'                               => 'check-cron-job-exists',
			'cron-get'                                  => 'get-cron-job',
			'cron-get-schedule'                         => 'get-cron-schedule',
			'cron-list'                                 => 'list-cron-jobs',
			'cron-list-schedules'                       => 'list-cron-schedules',
			'cron-next-run'                             => 'get-next-cron-run',
			'cron-overdue'                              => 'list-overdue-cron-jobs',
			'cron-run-now'                              => 'run-cron-job-now',
			'cron-status'                               => 'get-cron-status',
			'cron-update'                               => 'update-cron-job',
			'db-delete'                                 => 'delete-db-rows',
			'db-explain'                                => 'explain-db-query',
			'db-insert'                                 => 'insert-db-row',
			'db-optimize'                               => 'optimize-db-tables',
			'db-select'                                 => 'run-db-select-query',
			'db-stats'                                  => 'get-db-stats',
			'db-update'                                 => 'update-db-rows',
			'schema-extract'                            => 'extract-db-schema',
			'tables-list'                               => 'list-db-tables',
			'debug-log-clear'                           => 'clear-debug-log',
			'debug-log-read'                            => 'read-debug-log',
			'file-create'                               => 'create-file',
			'file-delete'                               => 'delete-file',
			'file-edit'                                 => 'edit-file',
			'file-read'                                 => 'read-file',
			'wp-config-edit'                            => 'edit-wp-config',
			'wp-config-read'                            => 'read-wp-config',
			'zip-create'                                => 'create-zip-backup',
			'zip-delete'                                => 'delete-zip-backup',
			'zip-download'                              => 'download-zip-backup',
			'zip-extract'                               => 'extract-zip-backup',
			'zip-list'                                  => 'list-zip-backups',
			'zip-upload'                                => 'upload-zip-backup',
			'font-face-create'                          => 'create-font-face',
			'font-face-delete'                          => 'delete-font-face',
			'font-face-get'                             => 'get-font-face',
			'font-face-list'                            => 'list-font-faces',
			'font-family-create'                        => 'create-font-family',
			'font-family-delete'                        => 'delete-font-family',
			'font-family-get'                           => 'get-font-family',
			'font-family-list'                          => 'list-font-families',
			'media-mimes-list'                          => 'list-upload-mime-types',
			'media-mimes-update'                        => 'update-upload-mime-types',
			'media-rename-file'                         => 'rename-media-file',
			'navigation-get-context'                    => 'get-navigation-context',
			'navigation-list-locations'                 => 'list-navigation-locations',
			'plugin-activate'                           => 'activate-plugin',
			'plugin-code-read'                          => 'read-plugin-code',
			'plugin-deactivate'                         => 'deactivate-plugin',
			'plugin-files-manage'                       => 'manage-plugin-files',
			'plugin-install'                            => 'install-plugin',
			'plugin-lifecycle-get-plugin'               => 'get-plugin-lifecycle-context',
			'plugin-list'                               => 'list-plugins',
			'plugin-structure-read'                     => 'read-plugin-structure',
			'plugin-update'                             => 'update-plugin',
			'update-check'                              => 'check-plugin-updates',
			'permalink-flush'                           => 'flush-permalink-structure',
			'permalink-get'                             => 'get-permalink-structure',
			'permalink-set'                             => 'set-permalink-structure',
			'site-icon-get'                             => 'get-site-icon',
			'site-icon-update'                          => 'update-site-icon',
			'site-logo-update'                          => 'update-site-logo',
			'site-title-get'                            => 'get-site-title',
			'site-title-update'                         => 'update-site-title',
			'tagline-get'                               => 'get-tagline',
			'tagline-update'                            => 'update-tagline',
			'site-health-info'                          => 'get-site-health-info',
			'site-health-status'                        => 'get-site-health-status',
			'site-maintenance-report'                   => 'get-site-maintenance-report',
			'get-cpt-taxonomies'                        => 'list-cpt-taxonomies',
			'taxonomy-set-term-image'                   => 'set-term-image',
			'theme-activate'                            => 'activate-theme',
			'theme-code-read'                           => 'read-theme-code',
			'theme-delete'                              => 'delete-theme',
			'theme-files-edit'                          => 'edit-theme-file',
			'theme-install'                             => 'install-theme',
			'theme-lifecycle-get-theme'                 => 'get-theme-lifecycle-context',
			'theme-list'                                => 'list-themes',
			'theme-structure-read'                      => 'read-theme-structure',
			'theme-update'                              => 'update-theme',
			'user-create'                               => 'create-user',
			'user-delete'                               => 'delete-user',
			'user-get'                                  => 'get-user',
			'user-list'                                 => 'list-users',
			'user-password-reset'                       => 'reset-user-password',
			'user-role-capabilities'                    => 'get-role-capabilities',
			'user-roles-list'                           => 'list-user-roles',
			'user-update'                               => 'update-user',
			'users-current-access'                      => 'get-current-user-access',
		);
	}
}
