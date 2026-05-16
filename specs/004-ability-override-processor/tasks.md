# Tasks: Ability Override Processor

**Input**: Design documents from `/specs/004-ability-override-processor/`
**Prerequisites**: [plan.md](plan.md) | [spec.md](spec.md) | [memory.md](memory.md) | [memory-synthesis.md](memory-synthesis.md) | [security-constraints.md](security-constraints.md)

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no incomplete dependencies)
- **[Story]**: User story label from [spec.md](spec.md)

---

## Phase 1: Setup

**Purpose**: Re-read feature memory and confirm the runtime override boundaries before implementation.

- [ ] T001 Read [memory-synthesis.md](memory-synthesis.md) and confirm there are no hard conflicts.
- [ ] T002 Review hook wiring constraints in [plan.md](plan.md) before editing `includes/Main.php`.

---

## Phase 2: Foundational

**Purpose**: Add the query and hook surfaces required by all runtime override behavior.

- [ ] T003 Add `get_all_overrides()` to `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`.
- [ ] T004 Add `includes/Modules/Sitewide/class-acrossai-ability-override-processor.php` with cache loading, Manager REST bypass detection, arg injection, blocked ability unregistration, and cache busting.
- [ ] T005 Wire processor `boot()` and `bust_cache()` hooks in `includes/Main.php` through the Loader.

---

## Phase 3: User Stories

**Purpose**: Complete behavior required by the specification.

- [ ] T006 [US1] Apply non-null override fields during `wp_register_ability_args`.
- [ ] T007 [US2] Unregister abilities with `site_allowed = false` after ability registration completes.
- [ ] T008 [US3] Ensure Manager REST requests skip override hooks and continue returning pure registry data.
- [ ] T009 [US4] Bust override cache after save, delete, and bulk reset paths.

---

## Phase 4: Validation and Memory Review

**Purpose**: Confirm implementation quality gates and review durable memory candidates.

- [ ] T010 Run PHPCS for modified PHP files.
- [ ] T011 Run PHPStan.
- [ ] T012 Run relevant PHPUnit tests.
  - Verify `is_manager_rest_request()` returns `true` for Manager GET requests (SEC-PLAN-001 test case).
  - Verify `load_overrides_cache()` treats non-array transient output as a cache miss (SEC-PLAN-003).
  - Verify `boot_hook()` / `bust_cache_hook()` instance wrappers delegate correctly to static methods (SEC-PLAN-002).
- [ ] T013 Review the implementation diff for durable memory candidates before completion.
