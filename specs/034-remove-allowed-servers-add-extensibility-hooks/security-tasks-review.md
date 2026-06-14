---
document_type: security-review
review_type: tasks
assessment_date: 2026-06-14
codebase_analyzed: acrossai-abilities-manager (Feature 034 tasks.md after migration removal)
total_files_analyzed: 9
total_findings: 4
overall_risk: HIGH
critical_count: 0
high_count: 1
medium_count: 2
low_count: 1
informational_count: 0
owasp_categories: [A01, A05]
cwe_ids: [CWE-285, CWE-732, CWE-1059]
field_summaries:
  document_type: "Always 'security-review'. Allows indexers to skip non-review documents."
  review_type: "Which command generated this document: audit, branch, staged, plan, tasks, or followup."
  assessment_date: "ISO 8601 date the review was performed (YYYY-MM-DD)."
  overall_risk: "Highest severity tier with active findings (CRITICAL, HIGH, MODERATE, LOW, INFORMATIONAL)."
  critical_count: "Number of Critical findings (CVSS 9.0-10.0)."
  high_count: "Number of High findings (CVSS 7.0-8.9)."
  medium_count: "Number of Medium findings (CVSS 4.0-6.9)."
  low_count: "Number of Low findings (CVSS 0.1-3.9)."
  informational_count: "Number of Informational findings."
  owasp_categories: "OWASP Top 10 2025 categories (A01-A10) that have at least one finding."
  cwe_ids: "CWE identifiers referenced in this document."
  finding_id: "Unique finding identifier (SEC-NNN) for cross-referencing and task linkage."
  location: "File path and line number of the vulnerable code (path/to/file.ext:line)."
  owasp_category: "OWASP Top 10 2025 category for this finding (AXX:2025-Name)."
  cwe: "Common Weakness Enumeration identifier with short name (CWE-NNN: Name)."
  cvss_score: "CVSS v3.1 base score (0.0-10.0). 9.0+=Critical, 7.0-8.9=High, 4.0-6.9=Medium, 0.1-3.9=Low."
  spec_kit_task: "Spec-Kit task ID for backlog tracking and remediation follow-up (TASK-SEC-NNN)."
---

# Security Review — Tasks: Feature 034 (Remove Allowed Servers + Add Extensibility Hooks)

## Executive Summary

**Overall risk: HIGH.** The migration drop is sound. However, a plugin-wide grep reveals the planning doc's file inventory is **materially incomplete** — `mcp_servers` is referenced in 5 production PHP files NOT enumerated by any task (Merger, Query, Exposure_Controller, Override_Processor, plus tests). One of those files (`AcrossAI_Abilities_Exposure_Controller`) implements **fail-closed access control** that gates MCP server visibility of abilities; removing it is the *intended* architectural change but is not explicitly acknowledged as a security-posture change anywhere in the spec, plan, or quickstart.

Implementing the current task list as-written will:
1. Fail T016's final-grep validation (because the listed files are not exhaustive).
2. Leave dead PHP code referencing a removed BerlinDB row property → PHPStan level 8 failures and runtime warnings.
3. Silently delete an access-control enforcement layer without spec-level acknowledgment that the plugin's authorization posture is changing.

**Recommended action**: revise `tasks.md` to enumerate the missing files explicitly (new tasks under US1), and add a one-paragraph "Security posture change" note to spec.md so the deletion of the enforcement layer is intentional and reviewable.

## Tasks reviewed

| Artifact | Used as |
|---|---|
| `specs/034-.../tasks.md` (30 tasks, post-migration-drop) | Primary review target |
| `specs/034-.../spec.md`, `plan.md`, `research.md`, `data-model.md` | Intent + constraint reference |
| `specs/034-.../security-constraints.md` (plan-phase) | Carry-forward findings |
| `specs/034-.../memory-synthesis.md` | Project-memory context |
| `docs/planning/034-...md` | Source of file-line inventory under audit |
| Plugin source tree (`includes/`, `src/`, `tests/`, `admin/`, `uninstall.php`) | Ground truth for `mcp_servers` references |

Plugin-wide `grep -rln "mcp_servers|mcpServers|MAX_MCP_SERVERS|sanitize_mcp_servers|mcpAdapterAvailable|handleServerToggle|handleAllServersToggle"` (excluding `node_modules`, `vendor`, `.git`, `build/`) hits 14 files. Cross-referencing against the planning doc inventory and current task list:

| File | In planning doc inventory? | Covered by a task? | Status |
|---|---|---|---|
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` | ✓ | T005 | OK |
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` | ✓ | T006 | OK |
| `includes/Utilities/AcrossAI_Sanitizer.php` | ✓ | T004 | OK |
| `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` | ✓ | T003 | OK |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` | ✓ | T007/T008 | OK |
| `includes/Utilities/AcrossAI_Abilities_Formatter.php` | ✓ | T010 | OK |
| `src/js/abilities/components/AbilityForm.jsx` | ✓ | T012, T017 | OK |
| `src/js/abilities/store/index.js` | ✓ | T013 | OK |
| `tests/jest/abilities/mcp-servers-checkbox.test.js` | ✓ | T014 | OK |
| `includes/Main.php` | — | — | **Orthogonal** (WPBoilerplate `McpServersList` package wiring per DEC-MCP-CAPABILITY-FILTER-WARN). NOT to be touched. T016 grep needs an exclusion. |
| `includes/Utilities/AcrossAI_Ability_Merger.php` | **NO** | **NO** | **MISSING — see HIGH-001** |
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` | **NO** | **NO** | **MISSING — see HIGH-001** |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php` | **NO** | **NO** | **MISSING — see HIGH-001 + MEDIUM-001 (security posture)** |
| `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` | **NO** | **NO** | **MISSING — see HIGH-001 + MEDIUM-001 (security posture)** |
| `tests/phpunit/abilities/AbilitiesExposureControllerTest.php` | implicit (T015 generic) | T015 generic | **MEDIUM-002 — needs per-file guidance** |
| `tests/phpunit/abilities/AbilitiesValidationTest.php` | implicit (T015) | T015 generic | Same |
| `tests/phpunit/abilities/AbilityOverrideInjectVariantATest.php` | implicit (T015) | T015 generic | Same |
| `tests/phpunit/sitewide/AbilityMergerTest.php` | implicit (T015) | T015 generic | Same |
| `tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php` | — | — | **Verify scope** — likely Feature 029 (pass_as_tool), not this feature. T016 grep needs context check. |
| `tests/jest/abilities/ability-form-user-access-section.test.jsx` | implicit | T015 implicit (but T015 wording focuses PHPUnit) | **MEDIUM-002** — Jest scope unclear |
| `tests/jest/sitewide/store.test.js` | **NO** | **NO** | **MEDIUM-002** |
| `tests/jest/sitewide/AbilityEditPanel.test.jsx` | **NO** | **NO** | **MEDIUM-002** |
| `build/js/abilities.js` | — | regenerated by T023 | OK |

## Vulnerability Findings

### SEC-T-001 — Task list missing files containing genuine `mcp_servers` references (HIGH)

- **finding_id**: SEC-T-001
- **location**: `specs/034-.../tasks.md` (US1 implementation block, T003–T016); `docs/planning/034-...md` (file inventory tables)
- **owasp_category**: A05:2025 — Security Misconfiguration (incomplete cleanup = mixed-state failure)
- **cwe**: CWE-1059: Incomplete Documentation (file inventory is wrong, leading to predictable incomplete implementation)
- **cvss_score**: 7.1 (High) — vector: AV:L/AC:L/PR:H/UI:N/S:U/C:N/I:H/A:H
- **spec_kit_task**: TASK-SEC-T-001
- **Description**: Four production PHP files in `includes/Modules/Abilities/` reference `mcp_servers` as either a row property, BerlinDB field, or runtime allowlist value, but no task in `tasks.md` mentions them:
  1. **`includes/Utilities/AcrossAI_Ability_Merger.php`** — line 41 (`'mcp_servers'` in some field list); line 195 (`'mcp_servers' => ( null !== $mcp_meta && array_key_exists('servers', $mcp_meta) ) ? $mcp_meta['servers'] : $ann_or_meta( 'mcp_servers' )`). This is the merger that combines DB row + registry data into the unified ability shape.
  2. **`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`** — lines 678–681 — `mcp_servers` non-string guard during BerlinDB save (encoding step + type coercion).
  3. **`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php`** — lines 106, 141–164 — **fail-closed enforcement** of `mcp_servers` allowlist at the exposure boundary. Documented in the file's docstring: "Row with non-empty mcp_servers + empty/unknown server_id → EXCLUDED (fail-closed)."
  4. **`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`** — lines 172, 176, 286, 325, 353, 366, 379–380, 445, 625, 678–683 — `mcp_servers` allowlist enforcement in the inject_override_args path and in the per-server filter hook implementation. Docstring: "mcp_servers allowlist — null = all servers; [] = deny; [...] = allowlist (strict)."
- **Why it matters**: Following `tasks.md` as written, T005 (Schema) removes the column definition and T006 (Row) removes the property. After T005/T006 land, the four files above still reference `$row->mcp_servers` and `'mcp_servers'` keys. Result:
  - **PHPStan level 8 (T022) FAILS** — accessing a property removed from the typed class is a level-8 error.
  - **Runtime warnings** on every ability save and every Exposure_Controller request — `$row->mcp_servers` produces a deprecation/notice in PHP 8.1+ for dynamic property access on a class that no longer declares it.
  - **T016 grep FAILS** — the final-validation grep enumerates the same patterns and will return non-empty results, blocking US1 checkpoint.
- **Recommendation**: Insert four new tasks in US1's implementation block (positioned BEFORE T011/T016, after T010), each covering one of the missing files. Suggested new tasks:
  - **T010a [P] [US1]** In `includes/Utilities/AcrossAI_Ability_Merger.php` (lines 41, 195): remove `mcp_servers` from the field list and the merge map.
  - **T010b [P] [US1]** In `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (lines 678–681): delete the `mcp_servers` non-string guard from the BerlinDB save normalization.
  - **T010c [US1]** In `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php` (lines 106, 141–164): remove the `mcp_servers` fail-closed enforcement block AND the surrounding docstring claims. Per MEDIUM-001, this is a security-posture change requiring spec acknowledgment first.
  - **T010d [US1]** In `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (lines 172–683, multiple sites): remove all `mcp_servers` enforcement — the `inject_override_args` injection (line 380 writes `$args['meta']['mcp']['servers']`), the per-server filter hook (line 678+), the row-presence checks (lines 325, 353). Audit carefully — this file ALSO contains `pass_as_tool` logic (Feature 029) that MUST NOT be touched (per research.md out-of-scope confirmation). Use targeted line-range edits, not blanket file rewrites.
  - **T016 (revise)** — add explicit grep exclusion for `includes/Main.php` (lines 306–314, which use `$mcp_servers_list` / `$mcp_servers_rest` variable names referencing the orthogonal WPBoilerplate `McpServersList` Composer package per DEC-MCP-CAPABILITY-FILTER-WARN). Suggested grep: `grep -rEn "mcp_servers|mcpServers|..." includes/ src/ tests/ admin/ uninstall.php | grep -v "McpServersList\|mcp_servers_list\|mcp_servers_rest"`.

### SEC-T-002 — Removing `mcp_servers` deletes a fail-closed access-control enforcement layer; security-posture change not acknowledged in spec (MEDIUM)

- **finding_id**: SEC-T-002
- **location**: `specs/034-.../spec.md` (Background + User Story 1); `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php` (lines 106–164); `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (per-server filter implementation)
- **owasp_category**: A01:2025 — Broken Access Control
- **cwe**: CWE-285: Improper Authorization
- **cvss_score**: 5.4 (Medium) — vector: AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:L/A:N — pre-launch context lowers exploitability but the impact is real
- **spec_kit_task**: TASK-SEC-T-002
- **Description**: The `Exposure_Controller` and `Override_Processor` files together implement a **fail-closed** access-control layer: an ability with a non-null `mcp_servers` allowlist is excluded from any MCP server whose `server_id` isn't on the list, AND requests from unknown/empty server contexts are excluded entirely. This is genuine authorization code, not just UI state. Removing the `mcp_servers` column means deleting this layer.

  Spec.md frames the change as removing "the per-ability 'Allowed Servers' **setting**" (UI-focused language) and "input surface **reduction**". Neither phrasing makes it visible to a reviewer that an **enforcement** layer is also being deleted. The User Story 1 narrative ("clean architecture, pure ability registry") describes the *intent* but not the *consequence*: post-merge, every ability is unconditionally exposed to every MCP server until the future `acrossai-mcp-manager` plugin ships and re-implements equivalent enforcement.
- **Why it matters**: Pre-launch, the practical exploitability is low — there are no production sites currently relying on this allowlist. BUT:
  1. A future reader auditing this feature will see "UI removed" in the spec and miss that authorization was also removed.
  2. If the plugin ships before `acrossai-mcp-manager` is ready, abilities are unconditionally exposed — by design, but undocumented.
  3. A future re-introduction of allowlist enforcement (perhaps in `acrossai-mcp-manager`) needs to know that the OLD enforcement was fail-closed; without that detail, a hook-based re-implementation might default to fail-open and silently weaken the eventual security posture.
- **Recommendation**:
  1. **Add a "Security posture change" note** to `spec.md` (under Background or as a new subsection before Functional Requirements) stating explicitly: "This feature deletes a fail-closed MCP-server allowlist enforcement layer previously implemented in `AcrossAI_Abilities_Exposure_Controller` and `AcrossAI_Ability_Override_Processor`. Post-merge, abilities are unconditionally exposed to all MCP servers until a future plugin (e.g., `acrossai-mcp-manager`) implements equivalent enforcement via the new extension hooks. This change is intentional and pre-launch-only; no production deployments are affected. The future re-implementation MUST be fail-closed by default to match the prior posture."
  2. **Add inline PHP comments** at the deletion sites in T010c/T010d explicitly stating "deleted fail-closed allowlist enforcement — see Feature 034 spec.md 'Security posture change' note" so a future grep finds the rationale.
  3. **Update `security-constraints.md`** to add this as a confirmed accepted posture change (not a vulnerability — an intentional architectural retreat).

### SEC-T-003 — Test cleanup tasks T014/T015 give insufficient per-file guidance for the 8 affected test files (MEDIUM)

- **finding_id**: SEC-T-003
- **location**: `specs/034-.../tasks.md` T014 + T015; test files listed in the inventory table above
- **owasp_category**: A05:2025 — Security Misconfiguration (test gap = silently-passing future regressions)
- **cwe**: CWE-732: Incorrect Permission Assignment for Critical Resource (analogue: test coverage incorrectly delegated)
- **cvss_score**: 4.3 (Medium) — vector: AV:L/AC:L/PR:L/UI:R/S:U/C:N/I:L/A:L
- **spec_kit_task**: TASK-SEC-T-003
- **Description**: T014 deletes one specific Jest file (`mcp-servers-checkbox.test.js`). T015 says "grep for mcp_servers in tests/phpunit/ and delete asserting lines." Neither task accounts for:
  - **3 Jest files outside the deleted one** that reference `mcp_servers`: `ability-form-user-access-section.test.jsx`, `sitewide/store.test.js`, `sitewide/AbilityEditPanel.test.jsx`. These contain assertions that will fail after the corresponding production code is deleted — but T015's wording focuses on PHPUnit ("`tests/phpunit/`") and may be read as not applying to Jest.
  - **`tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php`** which is Feature 029's pass_as_tool test. Per research.md, `pass_as_tool` is OUT OF SCOPE for this feature — but the grep flags this file. The developer needs explicit "verify whether the `mcp_servers` reference here is genuine (delete) or orthogonal/coincidental (leave alone)" guidance.
  - **`tests/phpunit/sitewide/AbilityMergerTest.php`** — if T015 is implemented as "delete the asserting lines", the developer might delete a Merger test method that ALSO covers other Merger behavior, regressing test coverage for the surviving Merger code.
- **Recommendation**: Replace T014/T015 with per-file tasks (one per test file from the inventory table) so each gets explicit guidance: "delete entire file", "delete this method", "delete these N assertion lines", or "verify out-of-scope reference and leave alone." Suggested expansion:
  - T014 [P] [US1] Delete `tests/jest/abilities/mcp-servers-checkbox.test.js` in full.
  - T014a [US1] In `tests/jest/abilities/ability-form-user-access-section.test.jsx`: grep + delete `mcp_servers` assertions only; keep other UserAccess tests.
  - T014b [US1] In `tests/jest/sitewide/store.test.js`: remove `mcp_servers` from any expected-fields lists.
  - T014c [US1] In `tests/jest/sitewide/AbilityEditPanel.test.jsx`: same as 14b for any payload/render assertions.
  - T015 [US1] In `tests/phpunit/abilities/AbilitiesExposureControllerTest.php`: delete tests covering the fail-closed mcp_servers allowlist (now removed enforcement); keep other exposure-controller tests.
  - T015a [P] [US1] In `tests/phpunit/abilities/AbilitiesValidationTest.php`: delete `mcp_servers` validation assertions; keep others.
  - T015b [P] [US1] In `tests/phpunit/abilities/AbilityOverrideInjectVariantATest.php`: delete `mcp_servers` inject assertions; keep others.
  - T015c [P] [US1] In `tests/phpunit/sitewide/AbilityMergerTest.php`: delete `mcp_servers` field-list assertions; keep other Merger tests.
  - T015d [US1] In `tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php`: **VERIFY first** — if `mcp_servers` reference is part of the pass_as_tool test setup (e.g., asserting that an ability with pass_as_tool=1 AND mcp_servers=[] behaves correctly), then the reference becomes meaningless after this feature and should be removed; if the test only tangentially names mcp_servers in a comment, leave alone.

### SEC-T-004 — T016 final-grep scope is incomplete (LOW)

- **finding_id**: SEC-T-004
- **location**: `specs/034-.../tasks.md` T016
- **owasp_category**: A05:2025 — Security Misconfiguration
- **cwe**: CWE-1059: Incomplete Documentation (verification step missing a directory)
- **cvss_score**: 2.6 (Low) — vector: AV:L/AC:H/PR:L/UI:R/S:U/C:N/I:L/A:N
- **spec_kit_task**: TASK-SEC-T-004
- **Description**: T016's grep covers `includes/ src/ tests/` but omits `admin/` and the root `uninstall.php`. Today's actual grep shows no references in those locations (so the gap is incidental, not active), but the principle of an exhaustive final-check should include them. The Main.php false-positive (orthogonal `McpServersList` package wiring) also needs an explicit exclusion so T016 doesn't report a "leftover" that's actually correct code.
- **Recommendation**: Replace T016's grep command with:
  ```bash
  grep -rEn "mcp_servers|mcpServers|MAX_MCP_SERVERS|MAX_SERVER_ID_LENGTH|sanitize_mcp_servers|mcpAdapterAvailable|handleServerToggle|handleAllServersToggle" \
    includes/ src/ tests/ admin/ uninstall.php \
    --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=build \
    | grep -v "McpServersList\|mcp_servers_list\|mcp_servers_rest"
  ```
  Documents (a) broader scope (`admin/`, `uninstall.php`), (b) explicit exclusion of orthogonal `WPBoilerplate\McpServersList` references in `includes/Main.php`, (c) build directory excluded since `npm run build` regenerates it.

## Confirmed Secure Patterns

| Pattern | Where confirmed | Why secure |
|---|---|---|
| **Sanitizer caller-then-callee ordering** | T003 → T004 | Avoids transient broken state where the wrapper still calls a deleted method. |
| **Manual JSX edit only** for AbilityForm.jsx | T012, T017 (explicit notes) | Honors `BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE` and `BUG-ABILITYFORM-JSX-MIXED-DEPTHS`. |
| **No migration shipped** | spec FR-011/FR-012, plan, tasks T005 | Eliminates the SQL DDL surface, the multisite race surface, the `%i` placeholder regression risk, and the BerlinDB v3 version-bump complexity — by simply not adding the code. |
| **REST silent-acceptance via removal, not allowlist tightening** | T007 + research.md | Removing the registered REST arg lets WP REST drop the unknown field silently. No `additional_properties: false` added → no new validation surface. |
| **SEC-001 REST required-field audit task present** | T011 | Catches a class of issue (form-only validation reliance) that would otherwise be invisible. |
| **Data-minimization warning + trust-model paragraph + reserved-keys registry** | T020 | Three of four LOW/INFO plan-review findings folded into one focused doc-hardening task. |
| **No bundled multi-story commit** | tasks.md Notes ("do NOT bundle US1 and US2 into a single commit") | Allows independent revert if US2 hooks misbehave under a future MCP manager plugin. |

## Memory Hub INDEX.md Row

```text
| specs/034-remove-allowed-servers-add-extensibility-hooks/security-tasks-review.md | tasks | 2026-06-14 | HIGH | C:0 H:1 M:2 L:1 | A01,A05 |
```

## Action Plan & Next Steps

1. **BEFORE implementation begins**:
   - **Revise `tasks.md`** to add T010a/T010b/T010c/T010d (the four missing files), expand T014→T014a-c (Jest files), expand T015→T015a-d (PHPUnit files), and revise T016's grep per SEC-T-004. Without these revisions, US1 is provably incomplete and will fail its own checkpoint.
   - **Revise `spec.md`** to add the "Security posture change" note per SEC-T-002. This is a one-paragraph spec edit that materially changes how a reviewer reads the feature.
2. **No critical findings** — `/speckit-security-review-followup` not required, but SEC-T-001's severity (HIGH) means the task-list revision is **not optional**. The plan-phase review (security-constraints.md) remains valid; this addendum supplements it.
3. **Durable memory capture candidates** (post-implementation, user-invoked):
   - **Pattern: "spec.md must surface security-posture changes when removing enforcement"** — when a feature removes auth/authz code (even if intentional), the spec must explicitly call it a posture change, not euphemize as "UI removal" or "input surface reduction." This finding (SEC-T-002) is a reusable lesson.
   - **Pattern: "planning-doc inventory must be grep-verified, not trusted as authoritative"** — Feature 034's planning doc was based on Feature 016's inventory at time-of-writing; intervening features (027/028/029) introduced new `mcp_servers` consumers that the inventory didn't track. Final tasks.md SHOULD include a plugin-wide grep step as the FIRST task in a removal-style feature, not the last verification.
4. **No `/speckit-memory-md-capture` auto-execution** — per project memory, user runs spec-kit commands manually. Both pattern candidates above are queued for post-implementation `/speckit-memory-md-capture-from-diff`.
