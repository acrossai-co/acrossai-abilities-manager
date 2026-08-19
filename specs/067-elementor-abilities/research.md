# Research: Elementor Ability Suite

**Feature**: 067-elementor-abilities
**Date**: 2026-08-13

Phase 0 resolves technical unknowns raised by the spec's Assumptions and by the "Constraints" line in Technical Context. Every decision captured with Decision / Rationale / Alternatives.

---

## R1. Elementor presence gate — where and how

**Decision**: Two-layer gate.

1. **Bootstrap gate** at `plugins_loaded` P20 in `AcrossAI_Core_Abilities_Bootstrap::register_abilities()`. Wrap the block of 80 free-Elementor ability instantiations in `if ( class_exists( '\Elementor\Plugin' ) ) { … }`. Wrap the 8 Pro-only abilities in a nested `if ( class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' ) ) { … }`.
2. **Runtime gate** — every Elementor ability's `execute()` method starts with `if ( ! class_exists( '\Elementor\Plugin' ) ) return $this->fail( 'elementor_missing', ... );`.

**Rationale**:
- Bootstrap gate keeps the ability library UI clean on non-Elementor sites (SC-001).
- Runtime gate is defense-in-depth for the edge case where Elementor is deactivated after registration (SC-003) — an already-registered ability could otherwise fatal on the first Elementor API call.
- `class_exists( '\Elementor\Plugin' )` is the canonical Elementor-detection idiom, matching the source plugin's convention.

**Alternatives considered**:
- **Single bootstrap-only gate**: fails on runtime deactivation — fatal error scenario.
- **Single runtime-only gate**: abilities appear in the library UI on non-Elementor sites but silently fail — bad UX and confusing.
- **`defined( 'ELEMENTOR_VERSION' )`**: works but less direct; `class_exists` is the standard.

**Impact**: minimal — the double gate adds one line per ability class and one wrapper block in bootstrap.

---

## R2. Elementor Pro detection idiom

**Decision**: `class_exists( '\ElementorPro\Plugin' ) || defined( 'ELEMENTOR_PRO_VERSION' )` — either signal counts. Used at bootstrap for the 8 Pro abilities.

**Rationale**:
- Both conditions can be true, but during Pro's own boot sequence one may come before the other. Using OR guarantees the gate opens as soon as either becomes true.
- Matches the source plugin's Pro-gate pattern.

**Alternatives considered**:
- **`is_plugin_active('elementor-pro/elementor-pro.php')`**: requires loading `wp-admin/includes/plugin.php`. Overkill for a class-existence check.
- **Runtime-only Pro gate**: 8 Pro abilities would appear in the UI on free-Elementor sites and fail — bad UX.

**Impact**: none beyond the bootstrap conditional.

---

## R3. Response envelope shape

**Decision**: `{ success: bool, post_id?: int, <payload_key>: any, message: string, error_code?: string }` on every ability.

On error: `success=false`, `message` is human-readable, `error_code` is machine-readable. Standard error codes: `elementor_missing`, `elementor_pro_missing`, `post_not_found`, `element_not_found`, `invalid_widget_type`, `invalid_template_type`, `insufficient_capability`, `force_replace_required`, `force_delete_required`, `invalid_path`, `descendant_destination`.

**Rationale**:
- Matches existing AcrossAI convention (Feature 066, `List_Blocks`, `Update_Post_Block`).
- Machine-readable `error_code` lets clients route errors without regex-matching the message.

**Alternatives considered**:
- **Throw WP_Errors from execute()**: breaks existing convention. Rejected.
- **Envelope with `data` wrapper**: adds one level of nesting without new information.

**Impact**: consistent handling across all 88 abilities.

---

## R4. `wp_slash()` and `_elementor_data` writes

**Decision**: Every write to `_elementor_data` post meta MUST wrap the JSON string in `wp_slash()` before `update_post_meta()`. Delegated to `Document_Repository::save_data()`.

**Rationale**:
- WordPress `update_post_meta()` and `wp_insert_post(['meta_input' => ...])` internally call `wp_unslash()`, which strips backslashes JSON uses to escape double quotes inside string values. Without `wp_slash()`, the JSON is silently corrupted and the Elementor page appears empty.
- The source plugin discovered this the hard way and documents it as a required pattern in its create-elementor-page playbook.
- Centralising the wrap in `Document_Repository::save_data()` prevents future ability authors from forgetting it.

**Alternatives considered**:
- **Save via `meta_input` in `wp_insert_post`**: same double-unslash bug.
- **Bypass meta API and write directly to `wp_postmeta` via `$wpdb`**: violates Constitution §II (no custom SQL).

**Impact**: single choke-point in `Document_Repository::save_data()` — every ability that writes to `_elementor_data` uses this helper.

---

## R5. Widget-schema output shape

**Decision**: `get-widget-controls` returns `{ success, widget_type, count, controls, message }`. `controls` is an array of objects, each `{ name, label, description?, type, section, default? }`. Optional `search` input filters case-insensitively across `name/label/description/section/type`.

**Rationale**:
- Matches the source plugin's `summarize_widget_controls()` output — proven format.
- `search` filter is critical for widgets with 100+ controls (e.g. Elementor Pro's Nav Menu, Posts widget).
- Ports directly into `Widget_Controls::summarize()` static helper.

**Alternatives considered**:
- **Return full raw control array**: massive payloads for large widgets; unfilterable.
- **Return only names**: too sparse — clients would need repeated calls to get types/defaults.

**Impact**: one utility method, consumed by both `get-widget-controls` and the widget-shortcut abilities (add-heading, add-text-editor, etc.) that internally validate against the widget's schema.

---

## R6. Force-guard idiom for destructive writes

**Decision**: Every destructive write against a populated document requires an explicit `force_replace=true` (for `update-data`, `clone-data`, and `update-element` when the payload is materially smaller than existing) or `force_delete=true` (for `delete-element` on top-level or populated elements). Returns `error_code: force_replace_required` / `force_delete_required` with a message stating what would be lost.

**Rationale**:
- Prevents accidental silent wipes when a client passes an incomplete payload — the single most-cited failure mode in the source plugin's changelog (v2.2.9, v2.3.11, v2.3.30 all hardened this).
- Explicit opt-in makes destructive intent visible in the audit log.

**Alternatives considered**:
- **No guard**: matches Elementor's own editor behaviour (last write wins) but too dangerous for machine callers that may generate malformed payloads.
- **Backup before write**: expensive; adds DB size; users don't typically want extra revisions from ability calls.

**Impact**: seven abilities need the guard — `update-data`, `clone-data`, `patch-data` (only when payload transforms are destructive), `update-element` (only when payload is smaller than existing), `delete-element`, `remove-element`, `empty-trash`.

---

## R7. Elementor version compatibility

**Decision**: Target Elementor 3.x+ (container era) and Elementor Pro 3.x+ (if installed). Do NOT support Elementor 2.x (section+column era) as first-class — clients using v2 can still work through raw `update-data` but the authoring primitives (`add-container`, container-focused audits) assume v3+.

**Rationale**:
- Elementor 3.0 shipped in July 2020. 2.x is effectively EOL and represents <5% of live sites per public stats.
- Container-first authoring is the direction Elementor is heading (v4 atomic era).
- Supporting both v2 sections/columns and v3 containers would double the ability surface for authoring primitives.

**Alternatives considered**:
- **Support both**: doubles maintenance; v2 users are a shrinking minority.
- **v3+ only, refuse v2**: breaks sites still on legacy Elementor. Rejected.

**Impact**: `add-container`, `add-widget`, `move-element`, etc. all assume the container-based JSON shape. Legacy v2 documents remain readable via `get-data` and writable via `update-data`.

---

## R8. Cache invalidation strategy

**Decision**: After every write, delegate to `Document_Repository::invalidate_cache()` which calls:
1. `\Elementor\Plugin::$instance->files_manager->clear_cache()` (Elementor's own cache clear).
2. `clean_post_cache( $post_id )` (WordPress post cache).
3. `delete_post_meta( $post_id, '_elementor_css' )` (per-post CSS cache — regenerated on next render).

Cache scope defaults to `post`; `clear-cache` ability supports both `post` and `site` scopes explicitly.

**Rationale**:
- Elementor caches rendered CSS per post in `_elementor_css` meta + generated file. Skipping the invalidation leaves stale CSS visible on the frontend.
- `clean_post_cache` ensures downstream WP cache layers (object cache, page cache) see the update.
- Centralising in the utility means adding a new page-cache invalidator (e.g. Cache Enabler, LiteSpeed) touches one file.

**Alternatives considered**:
- **Rely on Elementor's own save hooks**: not fired when writing directly to post meta.
- **Bypass invalidation for read-only abilities**: correct — only write abilities need cache clear.

**Impact**: `Document_Repository::save_data()` and the `clear-cache` ability share the invalidation path.

---

## R9. Category slug and namespace convention

**Decision**:
- Category slug: `acrossai-abilities-manager-elementor` (mirrors `acrossai-abilities-manager-block`, `acrossai-abilities-manager-content`).
- Ability namespace: `elementor/<verb>-<noun>` (e.g. `elementor/get-widget-controls`).

**Rationale**:
- Preserves the plugin-wide `acrossai/` prefix — clients can filter all AcrossAI abilities with a single namespace check.
- `elementor-` prefix on the slug body disambiguates from Gutenberg equivalents that already exist:
  - `acrossai/list-templates` (would conflict — we own no such ability today, but naming convention protects future Gutenberg template CRUD)
  - `acrossai/create-page` (already exists in `Content\Create_Page` — collision without prefix)
  - `acrossai/get-data` (too generic; the prefix names the subsystem)
- Category filter still lets clients enumerate all Elementor abilities via `wp abilities list --category=acrossai-abilities-manager-elementor`.

**Alternatives considered**:
- **`elementor/<verb-noun>`** (source plugin convention): breaks AcrossAI's single-namespace rule. Rejected.
- **`acrossai/<verb-noun>` + rely on category to disambiguate**: multiple existing collisions (`create-page`, etc.); brittle. Rejected.

**Impact**: 88 abilities all have the `elementor/` prefix. Clients can locate them either by slug prefix or by category.

---

## R10. Pattern-detection for `find-template-for-pattern`

**Decision**: Port the source plugin's keyword-scoring algorithm verbatim into `Template_Query::score_pattern_match()`. Algorithm: normalise the input pattern keyword, score each template by matching against title, meta description, and inspected content signatures (widget types present, container depth), rank descending, return top N.

**Rationale**:
- The source plugin's algorithm is battle-tested (v2.3.16 introduced it, subsequent versions refined ranking).
- Reusing avoids re-inventing pattern-matching heuristics — the audit changelog shows the source plugin tuned this over multiple releases.
- Keeps the ability's response contract (`{ success, templates: [{id, title, score, snippet}], message }`) stable for downstream clients.

**Alternatives considered**:
- **Full-text search only**: misses templates whose title doesn't mention the pattern keyword but whose content matches.
- **Content-signature only**: misses templates with sparse metadata.

**Impact**: ~65 LOC in `Template_Query::score_pattern_match()`. Consumed by `find-template-for-pattern` only.

---

## Summary of resolved unknowns

| # | Unknown | Resolution |
|---|---|---|
| R1 | Elementor presence gate | Two-layer: bootstrap `class_exists` gate + per-ability runtime gate returning `elementor_missing` error_code |
| R2 | Elementor Pro detection | `class_exists('\ElementorPro\Plugin') \|\| defined('ELEMENTOR_PRO_VERSION')` |
| R3 | Response envelope | `{ success, post_id?, <payload>, message, error_code? }` — matches existing convention |
| R4 | `wp_slash()` policy | Every `_elementor_data` write wraps JSON in `wp_slash()`; centralised in `Document_Repository::save_data()` |
| R5 | Widget-schema output | `{ name, label, description?, type, section, default? }` per control, with optional `search` filter |
| R6 | Force-guard idiom | `force_replace=true` / `force_delete=true` required for populated-document destructive writes |
| R7 | Elementor version target | 3.x+ (container era); v2 documents remain readable but authoring primitives assume v3+ |
| R8 | Cache invalidation | `Document_Repository::invalidate_cache()` — Elementor files manager + WP post cache + `_elementor_css` meta delete |
| R9 | Slug + category convention | Category `acrossai-abilities-manager-elementor`; abilities `elementor/<verb-noun>` |
| R10 | Pattern-scoring algorithm | Port source plugin's title + meta + content-signature keyword scoring |

**All NEEDS CLARIFICATION resolved. Ready for Phase 1 design.**
