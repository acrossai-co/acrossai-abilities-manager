# Feature Specification: Composer Package Updates — wpb-access-control v2 + main-menu absorbs addons-page

**Feature Branch**: `039-composer-package-updates`
**Created**: 2026-07-01
**Status**: Draft
**Input**: User description: "Update two composer packages with breaking releases. First, drop `acrossai-co/addons-page` and bump `acrossai-co/main-menu` to `^0.0.7` — main-menu now bundles AddonsPage (same class name `\AcrossAI_Addon\AddonsPage`, new constructor signature: drop the first positional `$menu_slug` arg; `$parent_slug` is now an optional third arg defaulting to 'acrossai'). `freemius/wordpress-sdk: ^2.0` is pulled in transitively. Second, bump `wpboilerplate/wpb-access-control` to `^2.0.0` which adds a per-consumer `$table_slug` arg to AccessControlManager and RuleTable. Adopt slug 'abilities' so this plugin owns `{prefix}abilities_access_control`. Do not migrate from the legacy `{prefix}wpb_access_control` table — no backward compatibility required. Update the activator to create the new per-consumer table the same way the plugin's other tables are created, and point uninstall cleanup at the new table name and option key (`wpb_ac_abilities_db_version`)."

A full implementation breakdown (TASK-1 through TASK-5, file paths, exact code shapes) is already authored at `docs/planning/039-composer-package-updates.md` and will feed `/speckit-plan` directly. This specification stays outcome-focused per Spec Kit conventions.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin keeps using the plugin after the dependency update (Priority: P1)

After the site owner deploys the plugin release that consumes these updated composer packages, every existing admin-facing feature continues to work without manual repair. The Add-ons submenu still renders under the shared AcrossAI parent menu, the per-ability Access Control panel still loads, and saving a rule still persists it. Nothing in the WP admin sidebar disappears, nothing fatals on activation, and no support ticket is opened because of the upgrade.

**Why this priority**: This is the only outcome end-users (site administrators) directly observe. If this fails, the upgrade has visibly broken the product; if this succeeds, the per-consumer-table refactor and the package consolidation are invisible to them — which is the intended quality bar for an infrastructure upgrade.

**Independent Test**: On a WordPress install with the previous plugin version active, replace the plugin folder with the updated release. Visit WP-Admin → AcrossAI → Add-ons (renders), AcrossAI → Abilities → any ability → Access Control panel (loads, shows existing or empty rule state), and save a rule (persists, no error toast). All three pass within five minutes of clicking around — no console errors, no PHP notices.

**Acceptance Scenarios**:

1. **Given** an admin opens WP-Admin → AcrossAI → Add-ons after the upgrade, **When** the page renders, **Then** the Add-ons UI shows installed/available add-ons and the Freemius bootstrap completes without an error notice.
2. **Given** an admin opens any per-ability edit page after the upgrade, **When** the page renders, **Then** the Access Control panel mounts, shows the current rule state, and the "Who can access" dropdown lists the available providers (role, user, capability).
3. **Given** an admin sets an Access Control rule on an ability after the upgrade, **When** they click Save, **Then** the rule persists, the API call returns success, and a fresh page load reflects the saved selection.

---

### User Story 2 — Fresh install creates the new per-consumer table automatically (Priority: P2)

A site owner installing the plugin for the first time on a clean WordPress site activates it, and the access-control storage is immediately provisioned alongside the plugin's other custom storage. They never see "table missing" errors, never need to run a database-repair tool, and never need to wait for a background admin request to provision the table.

**Why this priority**: Fresh-install correctness is foundational — without it, every new customer's first interaction with the plugin would fail. Lower priority than P1 only because the upgrade path covers the bulk of real-world deployments today.

**Independent Test**: On a clean WordPress install (no prior version of this plugin), activate the plugin. Inspect the database tables — the plugin's three custom tables exist immediately. Set a rule on an ability via the React UI — it persists successfully on the first attempt.

**Acceptance Scenarios**:

1. **Given** a clean WordPress install, **When** the admin activates the plugin for the first time, **Then** the plugin's dedicated access-control storage is provisioned in the same activation step as its other two custom tables.
2. **Given** a freshly activated plugin, **When** the admin sets an Access Control rule on the very first ability they create, **Then** the rule persists without requiring any prior page visit to "warm up" the storage.

---

### User Story 3 — Multi-plugin coexistence: storage isolation is provable (Priority: P3)

A site running this plugin alongside another AcrossAI-family plugin that also embeds the same access-control library has two completely isolated rule sets. Setting a rule in one plugin's UI cannot read, modify, or accidentally clobber a rule set in the other plugin's UI — even when both name a coincidentally identical resource key.

**Why this priority**: Forward-looking — the per-consumer table refactor exists precisely to enable safe multi-plugin embedding. No second AcrossAI plugin ships this isolation today, but the upgrade is what unblocks that future work. Verifying isolation now prevents a foot-gun later.

**Independent Test**: With the plugin active, inspect the database — confirm the plugin's storage uses a name that includes the dedicated slug, distinct from any name a hypothetical second consumer would use. (Direct multi-plugin install testing requires a second consumer, which is out of scope; database-level inspection is the proxy.)

**Acceptance Scenarios**:

1. **Given** the upgraded plugin is active, **When** an operator inspects the database for plugin-owned access-control storage, **Then** the storage name embeds the plugin-specific slug — not a generic shared name.
2. **Given** a future second AcrossAI plugin embedding the same library on the same site, **When** that plugin uses a different slug, **Then** rules set in one plugin's admin UI are not visible or modifiable from the other plugin's admin UI.

---

### Edge Cases

- **Pre-existing rules in the legacy shared storage**: On a site that previously held access-control data in the legacy shared location, the upgrade does NOT migrate that data. Admins relying on those rules will see an empty Access Control panel after the upgrade and must reconfigure rules. This is a deliberate constraint ("no backward compatibility") — the spec must document it so it surfaces in release notes.
- **Vendor directory missing on activation**: An operator who deploys plugin source without running `composer install` first attempts to activate the plugin. The existing fail-open admin notice path (the `class_exists` guard, the activation block) remains the sole user-facing signal — no new degradation path is introduced.
- **Library validates the storage slug and rejects it**: The upstream library validates the plugin's chosen slug at construction time and throws if it doesn't match the required format. The slug chosen by this plugin (`'abilities'`) is short, all-lowercase, alphanumeric, and known to pass; if a future maintainer mistakenly changes it to something invalid, plugin activation must fatal loudly rather than silently fall back to a shared table.
- **Legacy package still present in `vendor/` after partial update**: After dropping `acrossai-co/addons-page` from `composer.json`, an operator who runs only `composer install` (not `composer update`) on an old lockfile would still see the legacy package in `vendor/`. The Add-ons class name is identical across both packages — the Jetpack Autoloader picks one. Pinning the new versions in `composer.lock` and committing it ensures every fresh checkout resolves correctly.
- **Uninstall with "delete data" disabled**: The existing opt-in gate (`acrossai_abilities_uninstall_delete_data`) controls whether storage is dropped. Behavior matches today's: if the admin has not opted in, the plugin uninstalls without touching storage. The new per-consumer storage falls under the same gate — no new cleanup path is introduced outside that gate.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST remove its direct dependency on the deprecated `acrossai-co/addons-page` composer package and instead obtain the Add-ons admin UI from `acrossai-co/main-menu`.
- **FR-002**: The plugin MUST continue to render the Add-ons submenu under the shared AcrossAI parent menu with no observable change to its placement, label, capability requirement, or activation/license behavior.
- **FR-003**: The plugin MUST own a dedicated, plugin-specific access-control storage area whose physical name embeds a slug unique to this plugin (`abilities`) and is provably distinct from any name a sibling AcrossAI plugin would use.
- **FR-004**: The plugin's activation MUST provision the dedicated access-control storage in the same step that provisions the plugin's other custom storage — no "first admin request" warm-up requirement, no separate installer script.
- **FR-005**: The plugin MUST NOT migrate, copy, or otherwise touch any data residing in the legacy shared access-control storage. Existing rules in that legacy location are explicitly orphaned on upgrade and the admin is responsible for any cleanup.
- **FR-006**: When the admin has opted into data deletion on uninstall (via the existing `acrossai_abilities_uninstall_delete_data` setting), the plugin MUST drop the new dedicated access-control storage and remove its associated schema-version marker, mirroring the existing cleanup pattern for the plugin's other tables.
- **FR-007**: All access-control read/write operations from the per-ability admin UI MUST continue to function across the upgrade — list providers, get rule, save rule, clear rule. The operator-visible behavior of the React panel does not change.
- **FR-008**: When the access-control library is absent from `vendor/` (e.g., operator skipped `composer install`), the plugin MUST continue to load in degraded mode and surface the existing fail-open admin notice; it MUST NOT fatal, MUST NOT auto-deactivate, and MUST NOT introduce any new user-visible degradation path beyond what the v1 integration already provides.
- **FR-009**: When the Add-ons UI class is absent from `vendor/` (e.g., main-menu package missing), the plugin MUST continue to load and surface the existing fail-open admin notice for the Add-ons block; it MUST NOT fatal and MUST NOT auto-deactivate.
- **FR-010**: The three plugin settings (`acrossai_abilities_per_page`, `acrossai_abilities_log_retention_days`, `acrossai_abilities_uninstall_delete_data`) MUST be preserved across the upgrade — option names, stored values, and the host Settings page they appear in are unchanged.
- **FR-011**: The plugin's existing custom tables (`{prefix}acrossai_abilities`, `{prefix}acrossai_ability_logs`) and their schema-version markers MUST be unaffected by this feature.
- **FR-012**: The release notes accompanying this upgrade MUST inform site owners that pre-existing access-control rules stored in the legacy shared location will NOT carry over and must be reconfigured after upgrading.

### Key Entities *(include if feature involves data)*

- **Plugin-owned Access Control Storage** — the dedicated table that holds this plugin's access-control rules. Identified by a plugin-specific slug (`abilities`) so it can coexist with similar storage owned by other AcrossAI plugins on the same WordPress site. One row per (resource, rule-option) tuple. Created at plugin activation, dropped on opt-in uninstall.
- **Legacy Shared Access Control Storage** — the previous-version storage location that pooled rules from every consumer plugin embedding the access-control library. Explicitly orphaned by this upgrade; left in place on existing sites and never read or written by the upgraded plugin. Not deleted by uninstall.
- **Access Control Rule** — the user-facing unit of configuration: "who can access this ability". Comprises a rule type (role, user, capability, etc.) and a set of selected options (e.g. `[editor, author]`). One rule per (namespace, resource-key) pair. Unchanged in shape across the upgrade; only its storage location changes.
- **Schema Version Marker** — a small piece of metadata tracking which version of the access-control schema is installed for this plugin. Renamed from the legacy shared name (`wpb_access_control_db_version`) to a per-consumer name (`wpb_ac_abilities_db_version`) so multiple consumers can independently track their schema state.
- **Add-ons Admin UI** — the in-plugin discovery surface for available paid extensions, backed by Freemius. Logically unchanged; physically relocated from one composer package (`acrossai-co/addons-page`) to another (`acrossai-co/main-menu`). The class name consumers reference is deliberately preserved by upstream to keep this kind of migration boring.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After the upgrade is deployed to a representative test site, 100% of the three primary admin-visible features (Add-ons submenu, per-ability Access Control panel load, Access Control rule save) work on first attempt with zero PHP notices and zero JavaScript console errors.
- **SC-002**: On a fresh plugin activation against a clean WordPress install, the plugin's dedicated access-control storage is provisioned within the single activation request — verified within five seconds of clicking Activate.
- **SC-003**: After upgrading an existing install, 100% of the plugin's three persisted settings (per-page, log-retention, uninstall-delete-data) carry their pre-upgrade values across without admin intervention.
- **SC-004**: After uninstalling the plugin with "delete data" enabled, zero rows of the plugin's dedicated access-control storage remain in the database (verified via direct SQL inspection of the table named after the plugin slug).
- **SC-005**: Inspection of the database after upgrade shows the plugin's access-control table name embeds the plugin-specific slug, demonstrating that a sibling AcrossAI plugin using a different slug would land in a distinct, non-overlapping table.
- **SC-006**: Removing the vendor directory entirely (simulating a deploy without `composer install`) and visiting the WP admin produces the existing fail-open admin notice within one page load — no fatal error reaches the browser.
- **SC-007**: The number of direct entries in the plugin's composer `require` block decreases by exactly one (from removing `acrossai-co/addons-page`), with no new direct entries added.

## Assumptions

- The slug `'abilities'` is acceptable as the plugin's dedicated access-control identifier. It is short, lowercase, alphanumeric, fits the upstream library's `^[a-z0-9_]{1,32}$` validation, and reads naturally in the resulting database/option/REST names.
- The class name preserved by the upstream main-menu package (`\AcrossAI_Addon\AddonsPage`) is a stable contract for at least the lifetime of this release cycle; consumers may reference it directly without a wrapper or alias.
- The Add-ons admin UI shipping inside the main-menu package retains the same Freemius bootstrap contract (product id, public key, slug) that the standalone `acrossai-co/addons-page` package provided, with only the constructor positional order changing.
- Site owners who upgrade from a pre-v039 release accept that any access-control rules previously stored in the shared legacy storage will need to be reconfigured. Release notes will surface this; no in-product migration prompt is provided.
- `freemius/wordpress-sdk ^2.0` arriving transitively via the main-menu dependency is acceptable; the plugin does not require its own direct entry for the Freemius SDK.
- No other plugin currently active on a target install is independently embedding the legacy `wpb-access-control` v1 library at a version that would conflict with v2 — Jetpack Autoloader's "newest wins" guarantee handles any single-site coexistence concern automatically.
- The React component bundled with the access-control library v2 continues to work against the per-consumer REST namespace transparently — the JavaScript-side configuration string (`wpbAcConfig.namespace`) is not part of the per-consumer slug mechanism and does not need to change.
- The plugin's existing fail-open admin notice pattern (the `class_exists` guard plus the `manage_options`-gated notice) is the only required degradation path; no new notice or activation guard is introduced by this feature beyond what already exists.
