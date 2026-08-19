# Feature Specification: Slug rename — namespace to `acrossai/`, suffixes to verb-first

**Feature Branch**: `058-slug-rename-verb-first`
**Created**: 2026-07-25
**Status**: Shipped (documentation back-filled)
**Input**: User conversation — "the slug and the name is diff example get site title and the slug is site-title-slug can you tell me why we have done it" → "change the namespace from acrossai-abilities-manager to acrossai" → "do point number 3" (class-file rename) → "remove all the code related to migrations as this is kind of new plugin".

## Background

Ability slugs in `acrossai-abilities-manager` were inconsistent. Of the 219 registered abilities:

- ~140 were subject-first (`site-title-get`, `theme-activate`, `plugin-list`)
- ~80 were verb-first (`create-post`, `approve-comment`, `upload-media`)
- Several categories contained internal outliers (e.g. `update-check` living inside otherwise-`plugin-*` Plugins; `taxonomy-set-term-image` inside otherwise-verb-first Taxonomies).

The user noticed the slug/label mismatch during Custom-Ability edit-drawer inspection: labels read "Get Site Title" (verb-first) but slugs read `site-title-get` (subject-first). The `SLUG_PREFIX` constant in `AbilityForm.jsx` also hardcoded the wrong prefix (`acrossai-abilities/`) which caused the prefix box on the form to show the wrong string and the input field to display the entire slug instead of just the suffix.

Additionally, the 27-character namespace `acrossai-abilities-manager/` carried heavy token overhead on every LLM tool manifest — 219 abilities × 18 wasted chars ≈ 4 KB of pure namespace repetition per discovery response — while offering no benefit over a shorter plugin-owned namespace.

Motivation summary:

1. **LLM tool-use ecosystem alignment.** Every major function-calling spec (OpenAI, Anthropic tool use, MCP) uses verb-first names. The WordPress core MCP adapter's own built-ins follow the same pattern: `mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, `mcp-adapter/execute-ability`.
2. **Slug reads like label.** `get-site-title` matches "Get Site Title" — agents that hash description into slug guesses succeed instead of missing.
3. **Deterministic verb vocabulary.** Agents can pattern-match `get`/`list`/`create`/`update`/`delete`/`set`/… without reading full descriptions.
4. **Short namespace.** `acrossai/` (9 chars) vs the previous `acrossai-abilities-manager/` (27 chars) trims ~18 chars per slug on every discovery manifest.

The plugin is admittedly small-user at this point (per user), so the release ships as a breaking change with no backwards-compat aliases and no data migration.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Consistent verb-first slugs across every category (Priority: P1)

An administrator inspecting the Custom Abilities admin page or a developer/AI-agent consuming the `/wp-abilities/v1/abilities` REST endpoint sees every ability's slug in the same word order — always verb-first — matching the ability's human-readable label.

**Why this priority**: The whole feature exists to remove the cross-category inconsistency the user flagged. Without US1 nothing else matters. Ship-alone value: even if the namespace stays long, verb-first alone eliminates the "why does `theme-activate` sit next to `create-post`?" confusion.

**Independent Test**: `curl /wp-json/wp-abilities/v1/abilities?per_page=-1 | jq -r '.[] | .name' | sort` — every returned slug reads `<verb>-<subject>` (or `<verb>-<subject>-<qualifier>` for compounds), and every slug matches its corresponding `label` word order.

**Acceptance Scenarios**:

1. **Given** a fresh 0.0.16 install, **When** the administrator loads the Custom Abilities page, **Then** every visible ability slug shows `acrossai/<verb>-<subject>` form.
2. **Given** the ability with label "Get Site Title", **When** the client queries its slug, **Then** the returned name is `settings/get-site-title` (not `acrossai/site-title-get`).
3. **Given** any category (e.g. Plugins, Themes, Content, Comments), **When** slugs within that category are enumerated, **Then** every slug in that category follows the same verb-first form — no outliers.

---

### User Story 2 — Short namespace matching WP core convention (Priority: P2)

An AI agent enumerating ability tools over MCP receives a tool manifest whose namespace prefix is compact enough to match ecosystem norms — the same `<plugin-slug>/<verb>-<noun>` form the WP core MCP adapter uses for its own built-ins (`mcp-adapter/discover-abilities`, etc.).

**Why this priority**: Token overhead on tool manifests is real but non-blocking. The namespace could have stayed long if the user preferred; shortening is an ergonomic + convention-alignment win. Ship-alone value: for a very-few-users release, saving a few KB per manifest and matching WP core's shorter-namespace pattern is worthwhile.

**Independent Test**: `curl /wp-json/wp-abilities/v1/abilities/settings/get-site-title/run` returns the site title. All 219 slugs answer under the `acrossai/` prefix; zero answer under the old `acrossai-abilities-manager/` prefix.

**Acceptance Scenarios**:

1. **Given** a fresh 0.0.16 install, **When** any ability is executed via its new slug, **Then** the REST run endpoint responds with the expected payload.
2. **Given** an external caller hits the OLD URL `/wp-json/wp-abilities/v1/abilities/acrossai-abilities-manager/site-title-get/run`, **Then** the response is 404 (no backwards-compat alias) so the caller learns immediately to update.

---

### User Story 3 — UI Slug field shows correct prefix and only the suffix (Priority: P2)

An administrator opening the ability edit drawer sees the Slug input rendered as a Bootstrap-style input-group: a static prefix addon showing `acrossai/` and an editable text field containing ONLY the slug suffix (e.g. `get-admin-menu-context`), not the entire slug.

**Why this priority**: The pre-0.0.16 UI hardcoded `SLUG_PREFIX = 'acrossai-abilities/'` (wrong for every registered ability). Because the strip logic couldn't match the wrong prefix, it fell through and showed the entire slug in the input. Fixing the constant to `acrossai/` (matching the actual registered prefix) restores the intended UX.

**Independent Test**: Open the edit drawer for `admin-menu/get-admin-menu-context`. Confirm the prefix addon shows `acrossai/` and the input shows `get-admin-menu-context` (no `acrossai/` inside the input).

**Acceptance Scenarios**:

1. **Given** an ability whose slug is `settings/get-site-title`, **When** its edit drawer opens, **Then** the prefix addon shows `acrossai/` and the input shows `get-site-title`.
2. **Given** the administrator edits the suffix to `get-site-title-x` and saves, **Then** the persisted ability_slug is `acrossai/get-site-title-x` (prefix injected server-side, not duplicated).

---

### User Story 4 — Internal file structure matches slug word order (Priority: P3)

A future maintainer opening `includes/Abilities/Settings/Get_Site_Title.php` finds a `class Get_Site_Title` that registers slug `settings/get-site-title`. Class name, file name, and slug all read the same word order — no cognitive gap between the internal implementation and the external API.

**Why this priority**: Internal-only quality-of-life. External API is unaffected. Ship-alone value: makes code review + grep-by-slug easier for future work.

**Independent Test**: For every renamed ability, verify the file name = class name = `<verb>-<subject>` capitalised with underscores (e.g. `Get_Site_Title.php` contains `class Get_Site_Title` registering `settings/get-site-title`).

**Acceptance Scenarios**:

1. **Given** a slug `themes/activate-theme`, **When** its class file is located, **Then** the path is `includes/Abilities/Themes/Activate_Theme.php` and the class inside is `class Activate_Theme`.
2. **Given** the codebase after this release, **When** `find includes/Abilities -name '*_Get.php' -o -name '*_Update.php'` is run, **Then** results only include files whose slugs happen to end with those verbs (e.g. `Update_Wp_Core.php`), not subject-first artefacts.

---

### Edge Cases

- **Slug collision** on rename: two source slugs mapping to the same target. Prevented by the 1:1 mapping table (verified via `sort -u | wc -l` = 219 both sides).
- **Prefix-collision during string substitution**: `cron-delete` is a prefix of `cron-delete-all` and `cron-delete-schedule`. Perl look-behind + longest-first sort in the rename script prevents `cron-delete-all` being wrongly rewritten as `delete-cron-job-all`.
- **Path-vs-slug ambiguity during namespace sweep**: `/wp-content/plugins/acrossai-abilities-manager/…` contains the string `acrossai-abilities-manager/` as a filesystem path segment. Perl `(?<!/)` look-behind skips any occurrence preceded by `/`, preserving file paths while rewriting slug prefixes.
- **`plugin_basename` false positive**: `'acrossai-abilities-manager/acrossai-abilities-manager.php'` (plugin dir + main file) begins with the pattern being replaced. Look-behind can't distinguish it from a slug — first occurrence gets wrongly replaced. Caught during code review; explicit `Edit` correction reverts it.
- **ACL library namespace preserved**: `/wpb-ac/v1/{consumer}/rules/acrossai-abilities/{slug}` — the `acrossai-abilities` segment (no trailing `-manager`) is the ACL library's *namespace*, distinct from the slug prefix. Preserved via `(?<!/)` look-behind.
- **56 unchanged-suffix slugs still need namespace shortening**: The per-slug rename map only covers the 163 word-order flips. Since there's no data migration, this is a code-only concern — every unchanged-suffix registration file's `'name' =>` line was rewritten by the same perl sweep.
- **Class name for compound-noun subjects**: `wp-config-edit` → `Wp_Config_Edit`. Capitalisation rule = first-letter-only per hyphen-separated word (matches existing convention verified against `Wp_Core_Update.php` etc.).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Every ability in `includes/Abilities/**/*.php` MUST register its slug in the form `acrossai/<verb>-<subject>` where `<verb>` comes from the controlled vocabulary defined in the plan (get / list / create / update / set / delete / search / find / activate / deactivate / install / flush / reset / approve / unapprove / mark / upload / download / run / explain / refresh / check / read / edit / rollback / reinstall / optimize / extract / audit / inspect / bulk / assign / link / apply / review / rename / manage / copy).
- **FR-002**: The client-side `SLUG_PREFIX` constant in `src/js/abilities/components/AbilityForm.jsx` and `src/js/abilities/components/AbilitiesList.jsx` MUST be `'acrossai/'`.
- **FR-003**: The server-side prefix injection in `AcrossAI_Abilities_Write_Controller::create_ability()` (line 215) MUST prepend `acrossai/` (not `acrossai-abilities-manager/`) to any user-supplied slug suffix.
- **FR-004**: `AcrossAI_Abilities_Sanitizer::sanitize_slug_suffix()` MUST cap suffix length at `255 - len('acrossai/') = 246` chars (was `227`).
- **FR-005**: `AcrossAI_Abilities_Validator::validate_slug_suffix()` full-length check MUST compute against `'acrossai/' . $suffix`.
- **FR-006**: For each of the 163 renamed slugs, the corresponding PHP class file under `includes/Abilities/<Category>/` MUST be renamed to match the new slug (`Site_Title_Get.php` → `Get_Site_Title.php`) and the `class X` declaration inside MUST match the new file name. PSR-4 autoload picks up the new files automatically.
- **FR-007**: `AcrossAI_Core_Abilities_Bootstrap::register_abilities()` MUST reference every ability class by its new verb-first name (`new Settings\Get_Site_Title()`).
- **FR-008**: All PHPUnit test fixtures and helper files that reference an ability class by name MUST use the new class name.
- **FR-009**: All PHPUnit / Jest test fixtures that embed a slug string literal MUST use the new form (`settings/get-site-title`).
- **FR-010**: The plugin's REST admin namespace MUST change from `acrossai-abilities-manager/v1` to `acrossai/v1`; the client-side `abilitiesConfig.rest_namespace` in `admin/Main.php` MUST match; the constant `REST_NAMESPACE` in the two REST-controller classes MUST match.
- **FR-011**: `admin/Main.php::plugin_action_links()` MUST compare the incoming `$file` against the plugin's actual `plugin_basename` value `'acrossai-abilities-manager/acrossai-abilities-manager.php'` (unchanged — this is a filesystem identifier, not a slug).
- **FR-012**: The plugin version MUST bump to `0.0.16` in three places: `acrossai-abilities-manager.php` plugin header, `ACROSSAI_ABILITIES_MANAGER_VERSION` constant, and `README.txt` stable-tag.
- **FR-013**: `README.txt` MUST include a 0.0.16 changelog entry documenting the breaking rename in three bullets: what changed, class-file rename, "no auto-migration" note directing users to clear old rows manually.
- **FR-014**: No data migration ships — no PHP class, no wired hook, no uninstall cleanup entry. Users with pre-existing overrides or ACL rules under old slugs clear them manually from the admin UI.
- **FR-015**: No backwards-compatibility aliases. External callers hitting old URLs receive 404.
- **FR-016**: Historical spec, planning, and security-review docs (`specs/**` except this feature's own, `docs/planning/**`, `docs/security-reviews/**`) MUST NOT be rewritten by the sweep — they are historical records of past decisions.
- **FR-017**: `docs/memory/DECISIONS.md`, `docs/memory/WORKLOG.md`, `docs/memory/BUGS.md`, and `docs/memory/ARCHITECTURE.md` MAY be updated where they reference class file paths or slug examples that are now stale.

### Key Entities

- **Ability class**: PHP class extending `Ability_Definition`, one per registered ability, whose `ability()` method returns the `wp_register_ability()` args including the `'name'` (slug) key.
- **Slug**: String of form `<namespace>/<suffix>` where `namespace = 'acrossai'` (post-058) and `suffix = <verb>-<subject>[-<qualifier>]`, all kebab-case.
- **Rename map**: 163 old→new suffix pairs (e.g. `site-title-get` → `get-site-title`) driving the mechanical rename of files, classes, tests, docs, and (in the earlier commit that was later reverted) DB migration.
- **Bootstrap file**: `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — instantiates every ability class by name so PSR-4 autoload resolves each; must reference every class by its new name.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `grep -rhE "'name'\s*=>\s*'[^']+'" includes/Abilities --include="*.php" | grep -oE "'[^']+'" | sort -u | wc -l` = 219 (unique slug count unchanged).
- **SC-002**: Zero occurrences of `acrossai-abilities-manager/` as a slug prefix remain in scoped files after the sweep. `grep -rn "acrossai-abilities-manager/" includes tests src/js admin docs/memory docs/FEATURES.md README.txt --include="*.php" --include="*.js" --include="*.jsx" --include="*.md" --include="*.txt" | grep -v "/acrossai-abilities-manager/"` returns only intentional references (README changelog + docstring). File-path occurrences (`/wp-content/plugins/acrossai-abilities-manager/…`) are unaffected.
- **SC-003**: PHPUnit 170/170 pass after every commit in the branch.
- **SC-004**: Every ability class file's basename matches the class inside it (`grep -oE '^(?:final |abstract )?class \w+' includes/Abilities/**/*.php | awk -F: '{gsub("^class ", "", $2); split($1, a, "/"); n = a[length(a)]; sub(/\.php$/, "", n); if (n != $2 && n != "Category_Registrar" && n != "Ability_Definition") print $1}'` returns empty).
- **SC-005**: The Slug input on the ability edit form renders `acrossai/` as the prefix addon and the suffix-only in the input field. Manual verification via browser.
- **SC-006**: `curl /wp-json/wp-abilities/v1/abilities/settings/get-site-title/run` (with auth) returns the site title.
- **SC-007**: `grep -rn "Slug_Rename_Migration_058\|slug_rename_058_done" includes admin uninstall.php src tests README.txt` returns zero results (migration fully removed).

## Assumptions

- The plugin has very few users at this point (user's own statement, 2026-07-25); a breaking rename without data migration is acceptable.
- Sibling AcrossAI-org plugins (`acrossai-buddyboss-abilities`, `acrossai-mcp-manager`, `acrossai-model-manager`) use their own distinct namespaces (`acrossai-buddyboss-abilities/*`, etc.) — verified — so shortening this plugin's namespace to `acrossai/` introduces no collision with the sibling plugins.
- The composer PSR-4 autoloader maps `AcrossAI_Abilities_Manager\Includes\Abilities\<Category>\<Class>` to `includes/Abilities/<Category>/<Class>.php`, so renaming file + class name in lockstep is sufficient — no `classmap` regeneration needed.
- Jest test suite pre-existing ESM-transform failures on `@wordpress/data` imports are unrelated to this feature and out of scope.
- The user runs `/speckit-*` commands themselves (per `feedback_user_runs_speckit_commands`); this document is the input, not the output, of a formal `/speckit-plan` invocation.
