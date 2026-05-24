# Sitewide Module Decommission — Architecture Documentation Migration Plan

**Generated**: 2026-05-24
**Trigger**: `/speckit.architecture-guard.refactor-generator`
**Scope**: Non-blocking documentation and constitution drift caused by Feature 012 decommissioning `includes/Modules/Sitewide/`

---

## Current State

The plugin has two modules that both own `acrossai_abilities` DB operations: `Sitewide/` and `Abilities/`. The architecture memory documents describe this dual-ownership as the intended design.

```
includes/Modules/
├── Sitewide/           ← DB classes, REST controllers, Override Processor, Access Control
│   ├── Database/       ← AcrossAI_Sitewide_Table, AcrossAI_Sitewide_Schema, AcrossAI_Sitewide_Row, AcrossAI_Sitewide_Query
│   ├── Rest/           ← 4 sub-controllers + orchestrator
│   ├── AcrossAI_Ability_Override_Processor.php
│   └── AcrossAI_Sitewide_Access_Control.php
├── Abilities/          ← REST controllers, Processor (consumer), existing Query
└── Logger/
```

### Problems

1. **CONSTITUTION.md directory tree** lists `├── Sitewide/` in `includes/Modules/` — will be stale post-Feature 012.
2. **CONSTITUTION.md REST Controller Pattern** cites `includes/Modules/Sitewide/Rest/` as the canonical example directory — will be stale.
3. **ARCHITECTURE.md module map** contains 18+ lines describing `includes/Modules/Sitewide/` components as active module architecture.
4. **DECISIONS.md DEC-JSON-SIZE-GUARD** cites `AcrossAI_Sitewide_Query::save_override()` as the canonical location — moves to `AcrossAI_Abilities_Query`.
5. **DECISIONS.md DEC-BY-SOURCE-AUTHZ** cites `AcrossAI_Sitewide_Query::by_source()` as the canonical location — moves to `AcrossAI_Abilities_Query`.
6. **DECISIONS.md W-001** documents `acrossai_abilities_sitewide_after_save` as the cache-invalidation hook pattern — hook is deleted; replaced by three Abilities Write Controller hooks.
7. **DECISIONS.md** has no entry documenting the supersession of the "intentional reuse of Sitewide Schema/Row in Abilities_Query" design decision.
8. **DoD gap**: CONSTITUTION.md §VII requires unit tests for all new logic; `sanitize_ability_slug()` first-statement (new behavior added during CRUD port) has no unit test task.

---

## Target State

Single-module ownership. All architecture documents reflect Abilities as the sole owner of `acrossai_abilities` operations.

```
includes/Modules/
├── Abilities/          ← DB classes, REST controllers, Override Processor, Access Control, unified Query
│   ├── Database/       ← AcrossAI_Abilities_Table, AcrossAI_Abilities_Schema, AcrossAI_Abilities_Row, AcrossAI_Abilities_Query
│   ├── Rest/           ← 4 existing sub-controllers + orchestrator (unchanged)
│   ├── AcrossAI_Ability_Override_Processor.php    ← moved from Sitewide
│   └── AcrossAI_Abilities_Access_Control.php       ← renamed from Sitewide_Access_Control
└── Logger/
```

### Benefits

- Constitution directory tree accurately reflects the live codebase — future features can use it as a correct reference.
- DECISIONS.md canonical examples point to live files — `grep` links in decisions resolve correctly.
- W-001 hook pattern accurately describes the new three-hook cache invalidation design.
- Unit tests ensure `sanitize_ability_slug()` first-statement behavior is regression-protected.

---

## Migration Phases

### Phase 1: Code Implementation (Tasks T001–T026)

**Goal**: All PHP code changes complete; `includes/Modules/Sitewide/` deleted; static analysis clean.

- Tasks T001–T026 (existing implementation + verification tasks)
- **Coexistence**: During this phase, documentation still references Sitewide. This is intentional — do not update documentation until the code migration is verified complete.

**Coexistence**: Old documentation describes the old code; no documentation update creates ambiguity about what is currently live in this phase.

### Phase 2: Unit Test Coverage (Task T030)

**Goal**: New `sanitize_ability_slug()` behavior in ported CRUD methods is regression-tested.

- Task T030: `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_Override_Test.php`
- Covers all 4 ported methods for the new sanitization behavior and existing DB contracts
- Can run in parallel with Phase 3.

### Phase 3: Architecture Documentation Update (Tasks T027–T029)

**Goal**: All architecture memory documents reflect the post-refactor single-module structure.

- **T027** (P3): CONSTITUTION.md — remove `Sitewide/` from directory tree; update REST Controller Pattern example path from `Sitewide/Rest/` to `Abilities/Rest/`
- **T028** (P2): ARCHITECTURE.md — remove Sitewide module section; add/update Abilities module section to describe the now-consolidated DB classes, Override Processor, Access Control
- **T029** (P2): DECISIONS.md — update canonical `"Where to look next"` links in DEC-JSON-SIZE-GUARD and DEC-BY-SOURCE-AUTHZ to point to `AcrossAI_Abilities_Query`; update W-001 to describe the new three-hook pattern; add DEC-012-SUPERSESSION entry

**Coexistence**: Phase 3 can run after Phase 1 completes. No code changes are required in Phase 3.

---

## Coexistence Strategy

**Why coexistence?** Documentation updates have zero risk to the running codebase and can be deferred until the code is stable. Updating docs before code is verified creates a false impression that the refactor is complete.

**How**:
- Phases 1 and 2 operate on PHP source and test files — documentation is intentionally untouched.
- Phase 3 operates on `.specify/memory/CONSTITUTION.md`, `docs/memory/ARCHITECTURE.md`, and `docs/memory/DECISIONS.md` — no PHP files are touched.
- If the feature is rolled back after Phase 1, documentation was never updated and requires no rollback.

---

## Rollback Plan

- **Phase 1 rollback**: `git revert` all commits on `012-refactor-sitewide-abilities` branch; the Sitewide module is restored in full via git history.
- **Phase 2 rollback**: Delete `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_Override_Test.php`.
- **Phase 3 rollback**: `git revert` the documentation update commit; all memory docs revert to pre-Feature 012 state.

---

## Success Criteria

- [ ] `includes/Modules/Sitewide/` does not exist in the PHP source tree
- [ ] PHPStan level 8 passes with zero errors
- [ ] PHPCS passes with zero violations
- [ ] `grep -r "AcrossAI_Sitewide" includes/ admin/ --include="*.php"` returns zero results
- [ ] `.specify/memory/CONSTITUTION.md` directory tree contains no `Sitewide/` entry
- [ ] `docs/memory/DECISIONS.md` "Where to look next" links in DEC-JSON-SIZE-GUARD and DEC-BY-SOURCE-AUTHZ resolve to live files
- [ ] `docs/memory/DECISIONS.md` contains a `DEC-012-SUPERSESSION` entry
- [ ] Unit tests in `AcrossAI_Abilities_Query_Override_Test.php` pass
