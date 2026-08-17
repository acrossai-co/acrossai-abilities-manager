# Feature Specification: Rank Math Ability Suite

**Feature Branch**: `069-rank-math-abilities`
**Created**: 2026-08-17
**Status**: Draft
**Input**: User description: "Add abilities to our own plugin for Rank Math the same way we did for Elementor. Only add the abilities that are not in the seo-by-rank-math abilities list."

## Coverage baseline

Rank Math core (`seo-by-rank-math`) registers **13** abilities of its own under the prefix
`rank-math/`. That vendor plugin is not ours to change, so those 13 — and only those 13 — are the
coverage baseline:

`get-post-seo-meta`, `analyze-post-content`, `get-seo-scores`, `get-post-schema`, `audit-site-seo`,
`fix-site-seo`, `get-link-report`, `get-post-links`, `get-top-keywords`,
`get-ai-visibility-overview`, `get-ai-visibility-brand-insights`,
`get-ai-visibility-brand-queries`, `create-ai-visibility-brand`.

A third-party companion plugin (`mcp-abilities-rankmath`) registers 32 further abilities under the
prefix `rankmath/`. It is **explicitly not treated as coverage**: it is not ours to maintain, its
writes go through raw `update_option()` blobs that bypass Rank Math's sanitizer, and it gates every
ability on blanket `manage_options` regardless of Rank Math's Role Manager. Where this feature
re-implements something it also does, we do it correctly; the two coexist without slug collision
(`rank-math/` vs `rankmath/` vs `acrossai/rank-math-`).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Change a global SEO setting without corrupting the option blob (Priority: P1)

A client needs to change Rank Math's site-wide configuration — a title template for a post type, the
breadcrumb separator, a search-engine verification code, sitemap exclusions — and expects the change
to apply cleanly without disturbing any other setting.

**Why this priority**: this is the largest uncovered surface and the one where a naive implementation
does real damage. Rank Math's sanitizer assumes any field it has not been told about is single-line
text, and single-line text has its newlines stripped. A raw option write is therefore not merely
unvalidated — it silently destroys multi-line content.

**Independent Test**: on a site with Rank Math active, read the `general-webmaster` panel, write a
multi-line value to `custom_webmaster_tags`, then re-read. Verify the line breaks survived and that
every other key in `rank-math-options-general` is byte-identical to before the write.

**Acceptance Scenarios**:

1. **Given** a site with Rank Math active, **When** the client reads a settings panel, **Then** the
   response returns each field with its id, type, allowed values, default, and current value.
2. **Given** a client writes one field in a panel, **When** the write succeeds, **Then** every other
   setting in that option blob is unchanged.
3. **Given** a client writes a multi-line field, **When** the write succeeds, **Then** the line
   breaks are preserved in storage.
4. **Given** a client submits a field name that does not belong to the requested panel, **When** the
   ability runs, **Then** it refuses the whole write and names the offending field — it does not
   partially apply.
5. **Given** a client submits a protected field (`htaccess_content`, `analytics`, `usage_tracking`,
   …), **Then** the write is refused with a distinct error.
6. **Given** a client submits a repeatable group (opening hours, 404 exclusions) with non-sequential
   keys, **When** the write succeeds, **Then** the rows are stored as a list and not collapsed into
   a single row.

---

### User Story 2 — Fix a broken redirection (Priority: P1)

A client auditing redirections finds one pointing at the wrong destination and needs to correct it,
deactivate a batch of others pending review, and export the final set for the server config.

**Why this priority**: the redirection surface is otherwise nearly complete elsewhere but has no way
to *edit* an existing rule — only create and delete. Editing by delete-then-recreate loses the hit
counter and the rule's position.

**Independent Test**: create a redirection, update its destination through the ability, and verify the
rule's ID and hit count are preserved while the target changed.

**Acceptance Scenarios**:

1. **Given** an existing redirection, **When** the client updates its destination, **Then** the rule
   keeps its ID and statistics.
2. **Given** a client attempts a redirection whose source equals its destination, **Then** the
   response identifies the infinite loop, and distinguishes "created but auto-deactivated" from
   "refused".
3. **Given** a set of redirection IDs, **When** the client deactivates them, **Then** all are
   deactivated and the operation is reversible without a confirmation flag.
4. **Given** a client wants the rules as server config, **When** it requests Apache or Nginx format,
   **Then** the output matches what Rank Math's own exporter produces, and any rule with an invalid
   regex is reported in a warnings list.
5. **Given** trashed redirections exist, **When** the client lists redirections filtered to trashed,
   **Then** they are returned — so that emptying the trash is a discoverable operation.

---

### User Story 3 — Run a maintenance action safely (Priority: P1)

A client diagnosing a Rank Math problem needs to clear caches, rebuild tables, or convert legacy
blocks — and must not be able to trigger any of that by accident while exploring.

**Why this priority**: these are the most destructive operations in the suite. They must be reachable
(they are genuine fixes) but never reachable by accident.

**Independent Test**: call the maintenance ability with a valid tool and no confirmation. Verify no
work is performed and the response names the required flag. Repeat with confirmation and verify the
work happens.

**Acceptance Scenarios**:

1. **Given** any destructive ability, **When** it is called without `confirm: true`, **Then** it
   performs no work and returns an error naming the flag.
2. **Given** a maintenance tool whose module is inactive, **Then** the response says which module is
   missing rather than failing opaquely.
3. **Given** a tool that continues in the background after responding, **Then** the response marks
   itself asynchronous so the caller does not read success as completion.
4. **Given** a client discovers which tools are runnable, **When** it reads the status panel for
   tools, **Then** it gets the live catalogue rather than a hard-coded list.

---

### User Story 4 — Submit URLs for indexing and confirm they were accepted (Priority: P2)

After publishing, a client submits URLs to IndexNow and needs to know whether the submission was
accepted, and to inspect the history of past submissions.

**Independent Test**: submit a valid site URL, then read the log and verify the submission appears
with a timestamp.

**Acceptance Scenarios**:

1. **Given** IndexNow is configured, **When** the client submits URLs, **Then** the response confirms
   acceptance or returns a specific error for the HTTP status returned by the service.
2. **Given** IndexNow is not configured, **Then** the response says so rather than failing opaquely.
3. **Given** submissions exist, **When** the client reads the log, **Then** entries are returned with
   timestamps and filterable by manual vs automatic.

---

### User Story 5 — Understand and correct a post's SEO metadata (Priority: P2)

A client auditing content needs to see which posts have missing or weak SEO fields, then correct them
one at a time or in bulk.

**Why this priority**: Rank Math core can read one post's meta and can score posts, but cannot write
either, and cannot report *which fields* are missing across a set of posts.

**Independent Test**: create a post with no meta description, run the content audit, verify the post
appears with a `missing_seo_description` issue, then write a description and verify the issue clears.

**Acceptance Scenarios**:

1. **Given** published content with gaps, **When** the client runs the content audit, **Then** each
   post is returned with its specific issues and a per-issue count summary.
2. **Given** the same audit with issue filtering disabled, **Then** it returns all posts with their
   metadata — serving as a bulk metadata read.
3. **Given** a single post, **When** the client writes SEO meta, **Then** array-valued fields
   (robots) and flag fields (pillar, cornerstone) are stored in the format Rank Math expects.
4. **Given** a batch of posts, **When** the client bulk-writes meta, **Then** the response reports
   which rows were applied and which were skipped, with reasons.

---

### User Story 6 — Verify what Rank Math actually outputs (Priority: P2)

Having made changes, a client needs to confirm the rendered result rather than trust the stored
settings.

**Independent Test**: request the rendered head for a post URL and verify it contains the title and
canonical the settings imply.

**Acceptance Scenarios**:

1. **Given** headless support is enabled, **When** the client requests the rendered head for a URL,
   **Then** the actual output is returned.
2. **Given** headless support is disabled, **Then** the response names the setting that must be
   enabled.
3. **Given** the client calls this ability and then another ability in the same request, **Then**
   the second ability still behaves correctly.

---

### User Story 7 — Read Search Console performance data (Priority: P3)

A client wants to know how content is performing — clicks, impressions, position, index status —
without opening the Rank Math dashboard.

**Independent Test**: with Search Console connected, request the analytics summary for a 30-day range
and verify totals and a period-over-period comparison are returned.

**Acceptance Scenarios**:

1. **Given** Search Console is connected, **When** the client requests a summary for a date range,
   **Then** metrics for that range are returned with a comparison against the preceding period.
2. **Given** Search Console is not connected, **Then** the response says so rather than returning
   empty data that reads as "no traffic".
3. **Given** the URL Inspection table has not been created, **Then** the index-status ability names
   the maintenance tool that creates it.

---

### User Story 8 — Use credit-metered features without surprise spend (Priority: P3)

A client wants Content AI and AI Visibility features available, but must never spend credits
unknowingly.

**Independent Test**: with zero credits, call the keyword-research ability and verify it fails before
issuing any remote request.

**Acceptance Scenarios**:

1. **Given** no Rank Math account is connected, **Then** entitlement-gated abilities return a clear
   "account required" error — they remain registered and discoverable.
2. **Given** a zero credit balance, **When** a credit-consuming ability is called, **Then** it fails
   before making the remote call.
3. **Given** credits are available, **When** a credit-consuming ability runs, **Then** the response
   reports the balance before and after.

---

## Requirements *(mandatory)*

### Functional

- **FR-001**: The suite MUST register 61 abilities under the slug prefix `acrossai/rank-math-` and the
  category `acrossai-abilities-manager-rank-math`.
- **FR-002**: Every ability MUST carry `meta.acrossai.tab_group = 'rank-math'` so the admin
  Integrations page renders a "Rank Math" tab.
- **FR-003**: The category MUST NOT be registered when Rank Math is absent, and no ability class may
  be instantiated in that case.
- **FR-004**: Every `execute()` MUST re-assert Rank Math availability as defence in depth, and MUST
  return the response envelope — never a raw `WP_Error`, never a fatal.
- **FR-005**: The suite MUST NOT duplicate any of the 13 baseline abilities.
- **FR-006**: All global settings writes MUST route through Rank Math's own
  `Option_Center::save_settings()` with an explicit per-field type map. Raw option writes are
  prohibited.
- **FR-007**: A settings write MUST reject any field not declared for the requested panel, and MUST
  reject the six protected keys, without partially applying.
- **FR-008**: Repeatable group values MUST be stored as sequential lists.
- **FR-009**: Destructive abilities MUST require `confirm: true` and MUST declare
  `annotations.destructive = true`.
- **FR-010**: Reversible operations MUST NOT require confirmation.
- **FR-011**: Abilities whose backing work continues after the response MUST mark themselves
  asynchronous.
- **FR-012**: Permission checks MUST compose the house capability floor with Rank Math's own granular
  `rank_math_*` capability, overridable by a single documented filter.
- **FR-013**: Entitlement-backed abilities MUST register unconditionally when Rank Math is present and
  gate at runtime with a distinct error per gate flavour (PRO plugin, cloud account, credit balance).
- **FR-014**: Credit-consuming abilities MUST verify balance before issuing a remote request.
- **FR-015**: No ability class may reference a `\RankMath\*` symbol; all third-party access is
  confined to `includes/Abilities/Utilities/RankMath/`.
- **FR-016**: The rendered-head ability MUST NOT execute Rank Math's headless handler in-process.
- **FR-017**: Redirection listing MUST support filtering by status including trashed.
- **FR-018**: Module state changes MUST also refresh rewrite rules and fire Rank Math's
  `module_changed` action.

### Key Entities

- **Response Envelope** — the universal success/failure shape returned by every ability.
- **Field Spec** — one settings field's id, type, allowed values, default, and group schema.
- **Settings Panel** — a named group of Field Specs bound to an option blob and a capability.
- **Tool Descriptor** — a maintenance tool's id, target method, module requirement, and async flag.

## Success Criteria *(mandatory)*

- **SC-001**: 61 abilities appear under a "Rank Math" tab on the Integrations page when Rank Math is
  active, and the tab is absent when it is not.
- **SC-002**: Writing a single settings field changes no other setting's **effective value**, as read
  through Rank Math's own accessor. Byte-level differences in `wp_options` are permitted only for Rank
  Math's own one-time normalisation of legacy string toggles (`'off'` → `false`), which its own admin
  UI performs identically on any save — see research F8. A second identical write MUST be a true
  no-op.
- **SC-003**: A multi-line settings value round-trips with its line breaks intact.
- **SC-004**: No destructive ability performs work without `confirm: true`.
- **SC-005**: Every ability returns the envelope on both success and failure; none emits a fatal or a
  raw `WP_Error`, including when Rank Math is deactivated mid-session.
- **SC-006**: PHPStan level 8 and the feature test suite both pass.
- **SC-007**: A credit-consuming ability with a zero balance makes no remote request.
- **SC-008**: Enabling and disabling a module through the suite leaves no stale rewrite rules.

## Out of Scope

- The `.htaccess` editor, in either direction — a bad rule takes the whole site down.
- Version rollback and beta opt-in.
- Raw Rank Math option read/write — the plugin already ships generic option abilities.
- A bulk role-capability writer — `Helper::set_capabilities()` strips omitted roles, and the plugin's
  existing per-capability abilities already cover the need safely.
- Competitor Analyzer, Link Genius, news/video sitemap, podcast — no free-core implementation.
- Google account connect/disconnect — `wp_ajax_` only, no REST surface.
- The chunked long-running SEO-plugin importer; only detection is in scope.
- `setupWizard`, `disconnectSite`, `searchPage`, and the ACF/BuddyPress/Web Stories glue modules.
