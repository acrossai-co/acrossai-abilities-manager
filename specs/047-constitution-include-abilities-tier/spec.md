# Feature Specification: Constitution PATCH — Enumerate `includes/Abilities/` tier

**Feature Branch**: `047-constitution-include-abilities-tier` (proposed)
**Created**: 2026-07-13
**Status**: Draft (follow-up stub from Feature 046)
**Input**: Feature 046 introduced a new `includes/Abilities/` directory tier that is not enumerated in Constitution §I Directory Layout. plan.md V-02 documents this as an accepted deviation with a promised Constitution PATCH bump. This spec is that bump.

## Context

Constitution v1.4.8 §I Directory Layout enumerates:

```
includes/
├── Utilities/
└── Modules/
    ├── PerUser/
    ├── McpServer/
    ├── Abilities/
    ├── Webmcp/
    └── AbilityAPI/
```

Feature 046 added `includes/Abilities/` at the includes/-tier (sibling to
`Modules/` and `Utilities/`) as an isolated home for the absorbed
acrossai-core-abilities runtime. This tier holds heterogeneous capability
domains rather than a single feature module.

## Goal

Amend Constitution §I Directory Layout to enumerate the new tier so future
Spec Kit runs generate compliant plans by default. PATCH bump (1.4.8 → 1.4.9)
per §Governance ("clarifications, wording fixes, or non-semantic refinements").

## Recommended amendment

Insert into §I Directory Layout after the `Modules/` block:

```
includes/
├── Utilities/      # Shared utility functions, helpers, formatters
├── Modules/        # One subdirectory per feature module (self-contained)
│   ├── PerUser/
│   ├── McpServer/
│   ├── Abilities/
│   ├── Webmcp/
│   └── AbilityAPI/
└── Abilities/      # Absorbed capability library (Feature 046)
    ├── Utilities/  # Absorbed-code-scoped helpers (isolated from includes/Utilities/)
    ├── <Category>/ # Category folders (Block, Cache, Comments, …) — one per Abilities API category
    └── AcrossAI_Core_Abilities_Bootstrap.php  # Singleton orchestrator wired from Main.php
```

Add a §I paragraph explaining the tier's purpose:

> The `includes/Abilities/` tier holds capability-library code — collections
> of ability class implementations grouped by domain — that don't fit the
> "one module per feature area" module contract. Bootstrap orchestration
> for this tier lives in a single class (`AcrossAI_Core_Abilities_Bootstrap`)
> wired from `Main.php` via the Loader; ability class constructors extend
> the manager's `Ability_Definition` base (which itself hooks
> `acrossai_abilities_api_init`). The tier is not a replacement for
> `includes/Modules/` — new self-contained feature modules still go under
> `Modules/`.

Update Sync Impact Report at the top of the Constitution file per the standard
PATCH-bump template.

## Success Criteria

- SC-047-01: Constitution version bumps 1.4.8 → 1.4.9 with a proper Sync
  Impact Report entry.
- SC-047-02: Feature 046's V-02 deviation row can be marked resolved.
- SC-047-03: Future `/speckit-plan` runs generate structure trees that
  include the `includes/Abilities/` tier without requiring a new deviation.

## Non-goals

- No code changes.
- No new abilities.
- No behavioral changes.

## Dependencies

- Feature 046 merged.
- Requires manual invocation of `/speckit-constitution` (governance workflow).
