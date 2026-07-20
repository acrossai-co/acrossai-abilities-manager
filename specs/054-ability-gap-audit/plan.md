# Implementation Plan: Ability gap audit — track missing abilities vs. external tool inventory (0.0.13)

**Branch**: `054-ability-gap-audit` | **Date**: 2026-07-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification `./spec.md` + clarifications (Session 2026-07-20 Q1/Q2/Q3)
**Memory synthesis**: [memory-synthesis.md](./memory-synthesis.md)
**Security review**: [security-constraints.md](./security-constraints.md)
**Architecture review**: [architecture-review.md](./architecture-review.md)

## Summary

Audit-only release. No abilities are added; no PHP under `includes/Abilities/**` is touched. The PR ships:

1. **`specs/054-ability-gap-audit/`** — the frozen gap table (31 missing abilities across 10 domains) plus per-ability backlog tasks in `tasks.md`, so future implementation waves start from a durable reference instead of re-running discovery.
2. **README.txt** — `Stable tag` bump `0.0.12 → 0.0.13`, `= 0.0.13 =` Changelog block, `= 0.0.13 =` Upgrade Notice block, Screenshot #6 entry (Settings — Display + Upload Media Abilities).
3. **Version bumps** — `acrossai-abilities-manager.php:26` header `Version: 0.0.12 → 0.0.13`; `includes/Main.php:194` constant `ACROSSAI_ABILITIES_MANAGER_VERSION` from `'0.0.12'` to `'0.0.13'`.
4. **wp.org assets** — `git add` the 8 previously-untracked PNGs under `.wordpress-org/` (2 banners + 6 screenshots).

Future implementation waves are **one spec per domain**. Do not bundle. All future ability slugs follow the existing `acrossai-abilities-manager/<verb-noun>` convention (see the plugin's 187-ability naming corpus).

## Technical Context

**Language/Version**: PHP 8.1+ (Constitution §II floor). No JavaScript or SCSS changes.
**Primary Dependencies**: None added, none removed. `composer.json` untouched.
**Storage**: No new options, tables, or transients. No schema change.
**Testing**: PHPUnit and Jest unchanged (doc-only PR). Manual verification via grep commands in the Success Criteria of `spec.md`.
**Target Platform**: WordPress 6.9+, PHP 8.1+, multisite-compatible.
**Project Type**: WordPress plugin — the audit is a docs artifact under `specs/`.
**Performance Goals**: N/A — no runtime code changed.
**Constraints**: The inventory numbers cited in `spec.md` (187 registered, 31 missing) must be verified against `main` at commit time; if new abilities landed on `main` between spec authoring and PR merge, `spec.md` is updated before push (see Phase 3 verification).
**Scale/Scope**: 1 spec folder added (~7 files); 3 files edited (README.txt + plugin main file + includes/Main.php); 8 assets tracked.

## Constitution Check

Constitution version: **1.4.8**.

| Principle | Verdict | Notes |
|---|---|---|
| §I Modular Architecture | ✅ PASS | No module boundaries touched. `includes/Abilities/**` unchanged. Only the plugin header + version constant are edited outside of docs. |
| §II WPCS Compliance | ✅ PASS | No PHP code changes beyond a single-line version constant flip and a single-line header comment flip; PHPCS clean by inspection. PHPStan L8 unaffected. |
| §III User-Centric Design (NON-NEGOTIABLE) | ✅ PASS | No admin UI, form, or list surface added or changed. Screenshot #6 is documentation only. |
| §IV Security First (NON-NEGOTIABLE) | ✅ PASS | No new REST routes, permission callbacks, capability boundaries, or external HTTP calls. Zero attack surface delta. |
| §V Extensibility Without Core Modification | ✅ PASS | No vendor edits; no filter/action additions; no override registry changes. |

## Project Structure

### Documentation (this feature)

```text
specs/054-ability-gap-audit/
├── spec.md                 # Feature spec — includes the frozen gap table (source of truth)
├── plan.md                 # This file
├── tasks.md                # One backlog task per missing ability + release-housekeeping tasks
├── memory-synthesis.md     # Empty stub (audit-only — no cross-cutting memories to synthesize)
├── security-constraints.md # Note: no new security surface; future per-ability specs re-evaluate
├── architecture-review.md  # Note: no architecture delta; the audit is docs-only
└── checklists/             # Empty (.gitkeep) — reserved for future per-domain checklists
```

### Source Code (files touched by this PR)

```text
acrossai-abilities-manager/
├── README.txt                                                # Stable tag, Screenshots, Changelog, Upgrade Notice
├── acrossai-abilities-manager.php                            # Version header (line 26)
├── includes/Main.php                                         # ACROSSAI_ABILITIES_MANAGER_VERSION constant (line 194)
├── .wordpress-org/
│   ├── banner1544x500.png                                    # git add (previously untracked)
│   ├── banner772x250.png                                     # git add
│   ├── screenshot-1.png                                      # git add
│   ├── screenshot-2.png                                      # git add
│   ├── screenshot-3.png                                      # git add
│   ├── screenshot-4.png                                      # git add
│   ├── screenshot-5.png                                      # git add
│   └── screenshot-6.png                                      # git add
└── specs/054-ability-gap-audit/                              # NEW folder (see above)
```

**Structure Decision**: audit-only release. Doc surface lives under `specs/054-ability-gap-audit/` following the shape of `specs/053-*/`. The only runtime-facing edits are three version-string flips (`Stable tag`, plugin header `Version:`, runtime constant `ACROSSAI_ABILITIES_MANAGER_VERSION`) — no logic changes.

## Phases

### Phase 0 — Discovery (already done, captured in `spec.md`)

- External tool inventory reviewed against the plugin's registered abilities.
- Filtered to 31 gaps across 10 domains.
- Closest-existing mapping resolved per row.

### Phase 1 — Author spec-kit artifacts

- `spec.md` (this feature spec) — carries the frozen gap table.
- `plan.md` (this file) — describes the audit-only approach and verification steps.
- `tasks.md` — one task per missing ability + housekeeping tasks.
- Ancillary stubs — `memory-synthesis.md`, `security-constraints.md`, `architecture-review.md`, `checklists/.gitkeep`.

### Phase 2 — Release housekeeping edits

- `README.txt` Screenshots section adds #6.
- `README.txt` Changelog gets a `= 0.0.13 =` block above `= 0.0.12 =`.
- `README.txt` Upgrade Notice gets a `= 0.0.13 =` block above `= 0.0.12 =`.
- `README.txt:8` `Stable tag: 0.0.12` → `Stable tag: 0.0.13`.
- `acrossai-abilities-manager.php:26` `* Version: 0.0.12` → `* Version: 0.0.13`.
- `includes/Main.php:194` constant `'0.0.12'` → `'0.0.13'`.
- `git add .wordpress-org/{banner1544x500,banner772x250,screenshot-{1,2,3,4,5,6}}.png`.

### Phase 3 — Verification (before commit)

1. **Ability count sanity check** — Success Criteria SC-001:
   ```
   grep -rhE "'name'\s*=>\s*'acrossai-abilities-manager/" includes/Abilities/ \
     | grep -oE "acrossai-abilities-manager/[a-z0-9_/-]+" \
     | sort -u | wc -l
   ```
   Expect `187`. If it differs, update `spec.md` before pushing — do not paper over drift.

2. **Ability class file count** — Success Criteria SC-002:
   ```
   find includes/Abilities/ -name '*.php' -exec grep -l 'extends Ability_Definition' {} \; | wc -l
   ```
   Expect `187`.

3. **Bootstrap wiring is 1:1** — Success Criteria SC-003:
   ```
   grep -oE "new [A-Za-z]+\\\\[A-Z][A-Za-z_]+\(\)" includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php | sort -u | wc -l
   ```
   Expect `187`. Any ability class file with no matching `new …\ClassName()` in Bootstrap is a **bug** — patch Bootstrap and note the fix in `spec.md`, not a gap item.

4. **README.txt renders cleanly** — Success Criteria SC-004: paste into the WordPress.org readme validator; expect zero warnings.

5. **Version consistency** — Success Criteria SC-005: `README.txt` Stable tag, `acrossai-abilities-manager.php` Version header, `includes/Main.php` `ACROSSAI_ABILITIES_MANAGER_VERSION` constant, and topmost Changelog + Upgrade Notice entries all read `0.0.13`.

6. **wp.org assets tracked** — Success Criteria SC-006: `git ls-files .wordpress-org/*.png | wc -l` returns `8`.

7. **Static analysis unaffected** — Success Criteria SC-007: `composer run phpstan` and `composer run phpcs` (if defined) both green.

8. **Runtime surface unchanged** — Success Criteria SC-008: `git diff --stat main…HEAD` shows no diff under `includes/Abilities/**`, `src/`, `admin/`, `public/`, `build/`, `vendor/`, `node_modules/`, `tests/`.

### Phase 4 — Commit + PR

- Feature branch already exists: `054-ability-gap-audit`.
- Suggested commit message:
  ```
  Feature 054 — Ability gap audit + 0.0.13 release housekeeping
  ```
- Push to `origin/054-ability-gap-audit`; open PR against `main`.
- Suggested PR title: `Ability gap audit — track missing abilities vs. external tool inventory (0.0.13)`.
- PR body includes: link to `spec.md`, the frozen gap table (verbatim), the wp.org asset enumeration, the verification checklist results.

## Naming convention for future ability implementations

When each domain gets its own follow-up spec, every new ability slug MUST follow the plugin's existing convention: **`acrossai-abilities-manager/<verb-noun>`** where `<verb-noun>` is kebab-case. Examples derived from this audit:

| External name | Proposed plugin slug |
|---|---|
| `admin_menu.list_pages` | `admin-menu-list-pages` |
| `content_search.items` | `content-search-items` |
| `content_internal_link.suggestions_create` | `content-internal-link-suggestions-create` |
| `media.rename_file` | `media-rename-file` |
| `taxonomy.set_term_image` | `taxonomy-set-term-image` |
| `plugin_lifecycle.get_plugin` | `plugin-lifecycle-get-plugin` |
| `comments.bulk_update` | `comments-bulk-update` |

Categories for entire-domain-absent groups (Admin menu, Content index / search / linking) will need new `Category_Registrar.php` classes following the shape of `includes/Abilities/Content/Category_Registrar.php` and a new entry in `AcrossAI_Core_Abilities_Bootstrap.php::register_category_callbacks()`.

## Complexity Tracking

No constitution violations; no complexity to justify. This is a docs + version-bump PR.
