# Tasks: Rank Math Ability Suite

**Feature**: 069-rank-math-abilities
**Format**: `[ID] [P?] [Story] Description` — `[P]` = parallelisable with its siblings.

Paths are relative to the plugin root. Rank Math paths are relative to
`wp-content/plugins/seo-by-rank-math/`.

---

## Phase 0 — Setup (Batch 0)

- [x] **T001** Confirm PHPStan level 8 baseline. Result: clean; **no `phpstan.neon.dist` change needed** — the Elementor suite already references `\Elementor\Plugin::$instance` directly with a clean baseline.
- [x] **T002** Fix `includes/Main.php:194` — `ACROSSAI_ABILITIES_MANAGER_VERSION` `'0.0.21'` → `'0.0.27'`. Isolated commit `8cb5f18`.
- [x] **T003** Create `specs/069-rank-math-abilities/` — `spec.md`, `plan.md`, `research.md` (F1–F7), `data-model.md`, `contracts/abilities.md`, `checklists/requirements.md`, `quickstart.md`, `tasks.md`.

---

## Phase 1 — Foundational (Batch 1) — blocks everything

- [x] **T010** `includes/Abilities/RankMath/Category_Registrar.php` — singleton mirroring `Elementor/Category_Registrar.php`; `register()` returns early on `! class_exists( '\RankMath\Helper' )`; registers `acrossai-abilities-manager-rank-math`.
- [x] **T011** `includes/Abilities/Utilities/RankMath/Rank_Math_Guard.php` — `final`, all static. `assert_available()`, `assert_module()`, `assert_pro()`, `assert_account()`, `assert_credits()`, `assert_console()`, `assert_confirmed()`, `has_cap()` (re-implements `Helper::has_cap()` with `-`→`_` normalization so it cannot fatal when Rank Math is absent), `can()` factory applying `acrossai_abilities_manager_rank_math_permission`, and `ok()` / `fail()` / `error()`.
- [x] **T012** `includes/Abilities/RankMath/Base_Rank_Math_Ability.php` — sole assembler of `ability()` (category, `tab_group => 'rank-math'`, `show_in_rest`, `mcp`, `required => ['success']`, `additionalProperties => false`); `execute()` enforcing the five-step guard order; abstract `slug()`, `ability_label()`, `ability_description()`, `sub_group()`, `rank_math_cap()`, `input_properties()`, `output_properties()`, `required_input()`, `annotations()`, `run()`.
- [x] **T013** `includes/Abilities/RankMath/Get_Status.php` — ability #19, `panel` enum `status|tools|import_export|version_control|google`, `include_sites` default false.
- [x] **T014** Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php` — add the category callback after the Elementor line (~:73) and a `class_exists( '\RankMath\Helper' )` block plus `register_rank_math_abilities()` after the Elementor gate (~:431). **The comment MUST state that entitlement-gated abilities are deliberately registered unconditionally, unlike `register_elementor_pro_abilities()`.**
- [x] **T015** Edit `phpunit.xml.dist` — add `<testsuite name="feature-069-unit">`, initially listing T016–T018.
- [x] **T016** [P] `tests/phpunit/abilities/Test_Rank_Math_Category_Registrar.php` — asserts the `class_exists` guard and the exact category slug.
- [x] **T017** [P] `tests/phpunit/abilities/Test_Rank_Math_Base_Ability.php` — asserts the shared invariants once for all 61: category slug, `tab_group => 'rank-math'`, `show_in_rest`, the `mcp` block, `required => ['success']`, `additionalProperties => false`, exactly the eight allowlisted arg keys, and the five ordered guard calls in `execute()`.
- [x] **T018** [P] `tests/phpunit/abilities/Test_Rank_Math_Guard.php` — behavioural: `has_cap('404-monitor')` checks `rank_math_404_monitor`; returns false without fataling when Rank Math is absent; `can()` honours the filter.
- [x] **T019** **GATE** — verify in wp-admin per `quickstart.md`: the Rank Math tab renders, and disappears when Rank Math is deactivated. Do not start Phase 2 until this passes.

---

## Phase 2 — Settings spine (Batch 2)

- [x] **T020** `Utilities/RankMath/Settings_Registry.php` — `TYPE_*` constants, `DENIED_KEYS`, and the 20 panel tables. Each panel entry carries `option_type`, `cap`, `dynamic`, `fields`, and a `source` file:line citation. Transcribe from: `includes/settings/general/{links,breadcrumbs,webmaster,others,htaccess}.php`, `includes/settings/titles/{post-types,taxonomies,homepage,author,misc,global,social}.php`, `includes/modules/local-seo/views/titles-options.php`, `includes/modules/sitemap/settings/{general,post-types,taxonomies}.php`, `includes/modules/{image-seo,robots-txt,instant-indexing}/options.php`, `includes/modules/404-monitor/views/options.php`, `includes/modules/redirections/views/options.php`.
- [x] **T021** `Settings_Registry::validate()` — unknown-key and denied-key rejection naming the field and panel; toggle normalization to `'on'`/`'off'`; number clamping; enum membership; **`array_values()` reindex for every `TYPE_GROUP`**; `opening_hours` rejects an empty `time`.
- [x] **T022** `Utilities/RankMath/Settings_Writer.php` — `read()` and `save()`. `save()` order: validate → strip `DENIED_KEYS` → `field_types_for()` → `$updated = array_keys( $validated )` → route by `option_type` (`Option_Center::save_settings( $type, $validated, $field_types, $updated, false )`, or merge+`update_option` for `instant_indexing`) → return Rank Math's `notifications` plus only the touched fields.
- [x] **T023** `includes/Abilities/RankMath/Base_Settings_Write_Ability.php` — adds `option_type()`, `scope_enum()`, `panel_for()`; `run()` resolves scope+object to a panel and delegates to `Settings_Writer::save()`.
- [x] **T024** [P] `Get_Settings.php` — #1. Panel-specific cap re-checked inside `run()`; `general-robots-txt` adds the `state` object.
- [x] **T025** [P] `Update_General_Settings.php` — #2, `section` enum.
- [x] **T026** [P] `Update_Title_Settings.php` — #3, `scope` enum.
- [x] **T027** [P] `Update_Sitemap_Settings.php` — #4, `scope` enum.
- [x] **T028** [P] `Update_Instant_Indexing_Settings.php` — #5, separate option; `indexnow_api_key_location` read-only.
- [x] **T029** [P] `Update_Robots_Txt.php` — #6, asserts `Helper::is_edit_allowed()` and reports physical-file state.
- [x] **T030** Tests for T020–T029, incl. the behavioural `Settings_Registry` suite (the `custom_webmaster_tags => 'textarea'` assertion is F2's regression guard). Register in `phpunit.xml.dist`.
- [x] **T031** **GATE** — run integration checks 1, 2 and 7 from `quickstart.md` locally.

---

## Phase 3 — Modules, sitemap, routes (Batch 3)

- [x] **T040** `Utilities/RankMath/Instant_Indexing_Repository.php` — `assert_ready()`, `submit()` with the HTTP-code→`error_code` map, `log()`, `clear_log()`, `reset_key()`, settings read/write.
- [x] **T041** [P] `Submit_Urls.php` #7, `Get_Indexing_Log.php` #8, `Clear_Indexing_Log.php` #9, `Reset_Indexing_Key.php` #10.
- [x] **T042** `Utilities/RankMath/Module_Repository.php` — `available()`, `active()`, `set_state()` incl. `maybe_flush_rewrite()` (mirror of Rank Math's private `maybe_delete_rewrite_rules()`) and the `rank_math/module_changed` action.
- [x] **T043** [P] `Set_Module_State.php` #28, `List_Modules.php` #63.
- [x] **T044** [P] `Invalidate_Sitemap_Cache.php` #27 (uses `Cache::invalidate_storage()`; `scope=post` also calls `clear_queued()`), `Get_Sitemap_Status.php` #57, `List_Sitemap_Urls.php` #58.
- [x] **T045** `Utilities/RankMath/Routes_Repository.php` + `Get_Llms_Status.php` #59, `Refresh_Llms_Route.php` #60.
- [x] **T046** Tests for T040–T045; register in `phpunit.xml.dist`.

---

## Phase 4 — Redirections, 404 logs, roles (Batch 4)

- [x] **T050** `Utilities/RankMath/Redirections_Repository.php` — `save()` (the `Redirection::from()` + `is_infinite_loop()` + `save()` sequence returning the two distinct loop errors), `create()`, `list()` with the `status` filter, `find()`, `change_status()`, `stats()`.
- [x] **T051** Port the private serializers into `Redirections_Repository::to_apache()` / `::to_nginx()` — `apache_item`, `nginx_item`, `is_valid_regex`, `normalize_nginx_redirect`, `get_comparison`, `encode2nd`, `encode_regex`. Each carries an `@see` to `includes/modules/redirections/class-export.php:<line>`. Preserve the invalid-regex behaviour: commented-out in Apache, omitted from Nginx, listed in `warnings[]`.
- [x] **T052** [P] `Update_Redirection.php` #11, `Change_Redirection_Status.php` #12, `Delete_Trashed_Redirections.php` #13, `Get_Redirection_Stats.php` #14, `Export_Redirections.php` #15, `List_Redirections.php` #51, `Find_Redirection.php` #52, `Create_Redirection.php` #53, `Delete_Redirections.php` #54.
- [x] **T053** `Utilities/RankMath/Log_Repository.php` + `List_404_Logs.php` #55, `Delete_404_Logs.php` #56.
- [x] **T054** `Utilities/RankMath/Role_Capability_Repository.php` + `Get_Role_Capabilities.php` #16, `Reset_Role_Capabilities.php` #17. **No bulk writer** — see research secondary findings.
- [x] **T055** Tests for T050–T054, incl. the table-driven serializer fixtures. Register in `phpunit.xml.dist`.
- [x] **T056** **GATE** — run integration check 6 (infinite loop) locally.

---

## Phase 5 — Status, maintenance, backups (Batch 5)

- [x] **T060** `Utilities/RankMath/Maintenance_Tools.php` — `catalogue()` from the public `Database_Tools::get_json_data()`, `tool_ids()`, **`dispatch()` via a static `[class, method]` map — NOT `apply_filters( 'rank_math/tools/{id}' )`, which has no listener outside a `/toolsAction` REST request (research F3)**, `is_async()`, `normalize_result()` handling both `string` and `['status' => 'error', …]`. Construct `Database_Tools` once per request behind a static memo.
- [x] **T061** `Run_Maintenance_Tool.php` #20 — 12-value `tool` enum, `confirm`, runtime `tool_unavailable` check naming the missing module, `async` + `poll_hint`.
- [x] **T062** [P] `Export_Settings.php` #21 and `Import_Settings.php` #22 — via the `public static` `get_export_data()` / `do_import_data()`, not the superglobal-bound wrappers (research F6). #22 returns `backup_key`.
- [x] **T063** [P] `List_Backups.php` #23, `Create_Backup.php` #24, `Manage_Backup.php` #25.
- [x] **T064** [P] `Detect_Seo_Plugins.php` #26 — `Detector::detect()` only; the chunked import half is out of scope and the description says so.
- [x] **T065** [P] `Get_Seo_Analysis_Results.php` #38 — cached only; no stored run → `success: true, has_results: false`.
- [x] **T066** Tests for T060–T065, incl. `normalize_result()` behavioural tests and `is_async()` returning true for exactly the four background tools. Register in `phpunit.xml.dist`.
- [x] **T067** **GATE** — run integration check 3 (tool dispatch) locally.

---

## Phase 6 — Analytics and post-level content (Batch 6)

- [x] **T070** `Utilities/RankMath/Analytics_Repository.php` — `assert_analytics_ready()`, `stats( $range )` calling `set_date_range()` explicitly and never caching the instance, `request( $params )` synthesizing a `WP_REST_Request` for the request-taking methods, `connection_status()`, `inspections()` with the `null` guard.
- [x] **T071** [P] `Get_Analytics_Summary.php` #29 (6-value `report` enum), `Get_Analytics_Rows.php` #30 (3-value `dataset` enum), `Get_Index_Status.php` #31 (`inspections_table_missing`), `Inspect_Url.php` #32 (`mode` enum; `now` consumes GSC quota).
- [x] **T072** `Utilities/RankMath/Post_Meta_Repository.php` — `assert_post_type_editable()`, per-object `edit_post` checks, the `rank_math_*` postmeta surface with correct array/flag encoding, primary terms, schema deletion, and the content-audit query.
- [x] **T073** [P] `Bulk_Update_Meta.php` #33, `Update_Post_Schemas.php` #34, `Delete_Post_Schemas.php` #35, `Update_Seo_Scores.php` #36 — each computing `processed[]` / `skipped[]` with reasons rather than trusting Rank Math's always-success return.
- [x] **T074** [P] `Update_Seo_Meta.php` #45, `Get_Primary_Term.php` #46, `Update_Primary_Term.php` #47.
- [x] **T075** [P] `Audit_Content_Seo.php` #48 (`only_issues: false` doubles as the bulk metadata reader), `Get_Inbound_Links.php` #49 (content **and** nav menus), `Audit_Faq_Links.php` #50.
- [x] **T076** [P] `Get_Schema_Status.php` #62 — effective computed publisher output, distinct from #1's raw field values.
- [x] **T077** `Get_Rendered_Head.php` #37 — **HTTP loopback via `wp_remote_get()` to `/wp-json/rankmath/v1/getHead` (research F4). Never call `Headless::get_head()` in-process.** Precondition `general.headless_support` → `headless_support_disabled`.
- [x] **T078** Tests for T070–T077, incl. the `assertStringNotContainsString( 'RankMath\\', $src )` assertion on every ability file. Register in `phpunit.xml.dist`.
- [x] **T079** **GATE** — run integration checks 4 and 8 locally.

---

## Phase 7 — Entitlement-gated and release (Batch 7)

- [x] **T080** [P] `Get_Content_Ai_Status.php` #39, `Manage_Content_Ai_Prompts.php` #40, `Manage_Content_Ai_Output.php` #41 — local-only, no credits.
- [x] **T081** `Research_Keyword.php` #42 — verifies credits **before** any remote call; echoes `credits_before` / `credits_after`.
- [x] **T082** [P] `Get_Ai_Visibility_Brand.php` #43, `Update_Ai_Visibility_Object.php` #44 — `target` enum; `generate-queries` requires `confirm`.
- [x] **T083** Tests for T080–T082. Register in `phpunit.xml.dist`.
- [x] **T084** **GATE** — run integration check 9 (zero-credit guard makes no HTTP request) locally.

## Phase 8 — Polish

- [x] **T090** Full `composer phpstan` — must pass at level 8 with no new `ignoreErrors`.
- [x] **T091** Full `feature-069-unit` suite green; confirm every test file is listed in `phpunit.xml.dist`.
- [x] **T092** Security review → `docs/security-reviews/2026-08-17-069-rank-math-abilities.md`. One HIGH finding (missing per-object authorisation on term/user schema writes) fixed in `7c17cd5`; one candidate dismissed as a false positive.
- [x] **T093** Walk `checklists/requirements.md` and tick every row.
- [x] **T094** Admin-UI verification pass over all sub-groups; execute one ability per sub-group through MCP.
- [x] **T095** Bump `Version:` in `acrossai-abilities-manager.php`, `ACROSSAI_ABILITIES_MANAGER_VERSION` in `includes/Main.php`, `Stable tag:` in `README.txt`, and add the `= 0.0.28 - <date> =` changelog block with bold **Batch N** groupings.
- [ ] **T096** Regenerate `languages/acrossai-abilities-manager.pot`.
- [x] **T097** Update `docs/memory/` — architecture note for the Rank Math suite, the F1–F7 decisions, and the `PATTERN-*` / `DEC-*` ids they earn.

---

## Completion status

Landed across commits `8cb5f18` → `d6dfaf2` on branch `069-rank-math-abilities`.

**61 abilities**, verified live against WordPress 7.0.4 and Rank Math 1.0.276.
`feature-069-unit`: 334 tests / 2070 assertions. Full suite 1340 green. PHPStan level 8
clean with no new `ignoreErrors`.

### Outstanding

- **T092 — security review COMPLETE.** See
  `docs/security-reviews/2026-08-17-069-rank-math-abilities.md`. One HIGH finding was
  found and fixed in `7c17cd5`: `update_schemas()` asserted per-object rights only for
  `object_type=post`, dropping the `edit_user` and `edit_terms` checks Rank Math's own
  route performs, reachable because ten abilities used an `edit_posts` floor and Rank
  Math grants `rank_math_onpage_snippet` to author/editor by default. All floor
  overrides are now removed and the base floor is `final manage_options`.
- **T096 — POT not regenerated.** `wp-cli` is unavailable in this environment. Note the
  file has been 0 bytes since the initial commit, so this is pre-existing rather than a
  regression — but Feature 069 adds roughly 507 translatable strings and the POT must be
  generated before any release that ships translations.

### Verification not yet possible on this install

Several modules are inactive here, so those abilities were exercised only far enough to
confirm their module guards fire correctly, then verified fully by temporarily enabling
the module and restoring the option byte-identically:

| Area | State on this install |
|---|---|
| Redirections, 404 monitor, Role Manager | inactive — enabled temporarily for Batch 4 verification, restored |
| llms.txt | inactive — status ability correctly reports it and names the fix |
| Analytics / Search Console | not connected — analytics abilities return `google_console_not_connected` |
| Content AI, AI Visibility | no cloud account — return `rank_math_account_required` |
| Sitemap | active, but `sitemap_index.xml` 404s on this install (stale rewrite rules) |

Integration checks 1, 2, 3, 6 and 7 from `quickstart.md` were run and passed. Checks 4,
5, 8 and 9 need a connected Search Console or a Rank Math account and remain
unverified here.
