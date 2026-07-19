# Architecture Review — Feature 054

**Status**: Audit-only feature — no architecture delta in this PR.

## Modules touched (this PR)

| Module (per Constitution §I) | Change | Notes |
|---|---|---|
| Custom Ability Registration | None | 187 registered abilities unchanged; Bootstrap unchanged. |
| Ability Library | None | No categories added; no Library UI touched. |
| Sitewide Ability Management | None | No override registry changes; no admin UI touched. |
| Access Control | None | No policies added or modified. |
| Settings (shared main-menu) | None | No settings field added or modified. |

The only edits outside `specs/054-ability-gap-audit/` and `README.txt` are two single-line version-string flips (`acrossai-abilities-manager.php:26` header + `includes/Main.php:194` runtime constant). Neither crosses a module boundary.

## Constitution compliance

Constitution version: **1.4.8**. All five principles pass (see `plan.md` Constitution Check table). No violations; no complexity justifications needed.

## Architectural constraints on future per-domain waves (from `tasks.md`)

Every future ability implementation seeded by Phase 3 MUST respect:

- **Module count is locked at 5** (§I). New domains (Admin menu, Content search) do NOT create new modules — they add new **Category folders** under `includes/Abilities/` and register via `Category_Registrar.php` inside the existing Custom Ability Registration module.
- **Bootstrap wiring is 1:1** — every new ability class MUST be added to `AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()`. SC-002/SC-003 grep pair from `spec.md` is the enforcement mechanism.
- **Hooks register via the manager Loader** — `AC-HOOKS-MAIN` requires `add_action`/`add_filter` calls to trace back to `includes/Main.php` via `$this->loader->add_*()`. New categories' `Category_Registrar::instance()` callbacks must be added inside `register_category_callbacks( AcrossAI_Loader $loader )`.
- **No vendor modifications** (§V) — new features never patch `vendor/`; upstream `acrossai-co/main-menu` and other Composer packages remain read-only.
- **PHPStan L8 + PHPCS clean** (§II) — every new ability file passes both suites before commit.

## Two future-spec architectural risks flagged early

Both are for the follow-up specs, not this PR. Flagging here so the eventual spec authors see them.

1. **Content search (T501–T511)** — the 11-ability content-search domain likely needs a dedicated index table (either FT MySQL or a bespoke chunk table). Introducing a new table crosses a data-model boundary and requires:
   - A new activation-time migration in `includes/AcrossAI_Activator.php` following the shape of the existing `abilities_access_control` table creation.
   - An `uninstall.php` addition when the "delete all data on uninstall" opt-in is honored.
   - A schema version option for future migrations.
   - The follow-up spec MUST document the storage choice in its `plan.md` Technical Context.

2. **Plugin / theme lifecycle (T902, T903)** — capturing `activated_at` / `deactivated_at` / `updated_at` timestamps requires an option-backed event log. Choice: `wp_options` autoloaded row (fast, bounded by size cap) vs. a dedicated table (unbounded but adds schema). Recommend the option-backed approach with a rolling window (e.g. last 50 events per plugin/theme). The follow-up spec MUST call this out in Constitution Check §I to confirm the option approach does not violate module boundaries.

## Sign-off

No architecture concerns for this PR (docs + version bump). Every future spec seeded from Phase 3 requires its own `architecture-review.md` covering the module boundaries it touches.
