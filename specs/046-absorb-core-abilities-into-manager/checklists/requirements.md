# Specification Quality Checklist: Absorb Core Abilities Companion Into Manager

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-07-13
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- Items marked incomplete require spec updates before `/speckit-clarify` or `/speckit-plan`

## Validation results (post-clarify — 2026-07-13)

All 16 checklist items pass after 4 clarification questions were answered and
integrated. Notes on each category:

**Content Quality** — spec.md frames the migration through four user stories
(site admin, downstream integrator, admin retaining Core settings inside the
Abilities tab, plugin maintainer) without naming PHP namespaces, class names,
folder paths, or the words "PSR-4"/"autoloader"/"composer"/"webpack".
Implementation-side details from the user's briefing (namespace rewrites,
`AcrossAI_Core_Abilities_Bootstrap`, `define_public_hooks`) are deliberately
absent from spec.md and belong in plan.md.

**Requirement Completeness** — no `[NEEDS CLARIFICATION]` markers remain. Every
FR (FR-001 through FR-014) uses a testable assertion (MUST / MUST NOT). Every SC
(SC-001 through SC-007) uses a measurable predicate (percent, count, seconds,
zero references). Edge cases enumerate the fresh-install no-op, activation
migration idempotency, missing WP Abilities host, hard-coded caller paths, and
new upstream categories. Assumptions call out companion-absent target sites,
slug-based downstream references, WP Abilities API availability, shared settings
host, absence of companion database tables, absence of moved assets, and .pot
regeneration follow-up.

**Feature Readiness** — each FR maps to at least one acceptance scenario in the
user stories. User stories cover admin-facing consolidation (US1), integrator
non-breakage (US2), admin retention of Core settings inside the existing
Abilities tab with unified uninstall opt-in (US3), and maintainer quality-gate
consolidation (US4). SC-001 through SC-007 provide independently verifiable
metrics that map back to the FRs without leaking implementation.

## Clarification session — 2026-07-13

Four clarifying questions asked, all answered and integrated:

- **Q1 (Duplicate registration UX)** — Non-applicable: companion plugin will
  not exist on target sites at activation. FR-012, US1 Acceptance Scenario 3,
  two edge cases, and SC-008 removed.
- **Q2 (Category label wording — revised)** — Rebrand every occurrence of
  "Acrossai Core Abilities" to "Acrossai Abilities Manager" throughout the
  absorbed code: admin-visible label text, category slugs (from
  `acrossai-core-abilities-<domain>` to `acrossai-abilities-manager-<domain>`
  across all 17 categories), class names, function names, and any other
  internal identifiers. This is an intentional breaking change for downstream
  callers referencing the old slugs. FR-001, FR-002, FR-006, FR-006a, US2,
  Key Entities (Ability Category, Ability), SC-002, Assumptions, and a new
  edge case for hard-coded legacy slugs were all updated.
- **Q3 (Core admin surface + option keys)** — Merge Core fields into the
  existing Abilities tab (no separate Core tab / no `?tab=core` URL); migrate
  companion option keys at activation to manager-branded keys. FR-004, FR-005,
  FR-008 updated; US3 rewritten; Key Entities updated; SC-004 updated; new
  edge case for activation idempotency added.
- **Q4 (Two uninstall opt-ins on one tab)** — Consolidate into the manager's
  single existing opt-in (`acrossai_abilities_uninstall_delete_data`) which
  governs both manager data and the absorbed extra-MIME-types option. FR-004,
  FR-008, US3, Key Entities, SC-004, and edge-case idempotency section updated.

Deferred to `/speckit-plan` (not blocking spec):

- Manager plugin semver bump (patch vs minor vs major) — release decision, not
  spec-level.
- Boot-time performance target for adding 201 more classes at
  `plugins_loaded @ P20` — plan-level implementation concern; SC-004 already
  covers admin-interaction save latency.
- Ability-class instantiation order preservation — plan-level, documented in
  the planning doc `docs/planning/046-absorb-core-abilities-into-manager.md`.
