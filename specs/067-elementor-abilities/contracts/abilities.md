# Ability Contracts — Feature 067

**Feature**: 067-elementor-abilities
**Date**: 2026-08-13

Every ability under this feature registers via `wp_register_ability( 'elementor/<slug>', [...] )`, is gated on `class_exists( '\Elementor\Plugin' )`, and returns the shared response envelope defined in [data-model.md § Response Envelope](../data-model.md#entity-response-envelope). The 8 Pro-gated abilities additionally require `class_exists( '\ElementorPro\Plugin' )`.

**Shared cross-cutting behaviour** (documented once here, applied by every ability):

- **Category**: `acrossai-abilities-manager-elementor`
- **Namespace**: `elementor/<verb-noun>`
- **Capability model** (writes): `manage_options` AND `edit_posts` globally + `edit_post($post_id)` per-post
- **Capability model** (reads scoped to a post): `manage_options` AND `edit_posts` globally + `read_post($post_id)` per-post
- **Post-type gate** (writes): refuse `revision`, `nav_menu_item`, `custom_css`, `customize_changeset`, `oembed_cache`, `user_request`
- **wp_slash policy**: every `_elementor_data` write wraps JSON in `wp_slash()` before `update_post_meta()`
- **Cache invalidation**: every write triggers `Document_Repository::invalidate_cache()` — Elementor files manager + WP post cache + `_elementor_css` meta delete
- **Force guards**: destructive writes against populated documents require `force_replace=true` / `force_delete=true`
- **Runtime gate**: every `execute()` first checks Elementor presence and returns `{ success:false, error_code:'elementor_missing' }` if absent

**Standard error codes**: `elementor_missing`, `elementor_pro_missing`, `post_not_found`, `element_not_found`, `invalid_widget_type`, `invalid_template_type`, `insufficient_capability`, `force_replace_required`, `force_delete_required`, `invalid_path`, `descendant_destination`, `multiple_locations`, `pattern_not_found`.

---

## Group 1 — Document / element operations (17 abilities)

### 1.1 `elementor/get-data`
**Type**: read
**Input**: `{ post_id: int }`
**Output** (success): `{ success, post_id, data: array<Element>, page_settings: object, message }`
**Errors**: `post_not_found`, `post_type_forbidden`, `insufficient_capability`

### 1.2 `elementor/update-data`
**Type**: write · destructive · non-idempotent
**Input**: `{ post_id: int, data: array<Element>, page_settings?: object, force_replace?: bool, cache_scope?: 'none'|'post'|'site' }`
**Output**: `{ success, post_id, element_count, cache: { scope, cleared }, message }`
**Errors**: standard + `force_replace_required` (when replacing a populated doc without the flag)

### 1.3 `elementor/patch-data`
**Type**: write · destructive · non-idempotent
**Input**: `{ post_id: int, find: string, replace: string, cache_scope?: string }`
**Output**: `{ success, post_id, replacements: int, message }`
**Errors**: standard

### 1.4 `elementor/clone-data`
**Type**: write · destructive · non-idempotent
**Input**: `{ source_post_id: int, target_post_id: int, include_page_settings?: bool, force_replace?: bool }`
**Output**: `{ success, source_post_id, target_post_id, message }`
**Errors**: standard + `force_replace_required`

### 1.5 `elementor/get-element`
**Type**: read
**Input**: `{ post_id: int, element_id: string }`
**Output**: `{ success, post_id, element: Element, path: array<string>, message }`
**Errors**: standard + `element_not_found`

### 1.6 `elementor/find-elements`
**Type**: read
**Input**: `{ post_id: int, element_type?: 'container'|'widget'|'section'|'column', widget_type?: string, contains?: string, include_path?: bool }`
**Output**: `{ success, post_id, elements: array<Element+path?>, count, message }`
**Errors**: standard

### 1.7 `elementor/update-element`
**Type**: write · destructive · non-idempotent
**Input**: `{ post_id: int, element_id: string, element: Element, force_replace?: bool, cache_scope?: string, allow_legacy_style_preservation?: bool }`
**Output**: `{ success, post_id, element_id, element, message }`
**Errors**: standard + `element_not_found` + `force_replace_required`

### 1.8 `elementor/merge-element-settings`
**Type**: write · idempotent (same input = same result)
**Input**: `{ post_id: int, element_id: string, settings: object, cache_scope?: string }`
**Output**: `{ success, post_id, element_id, element, changed_keys: array<string>, message }`
**Errors**: standard + `element_not_found`

### 1.9 `elementor/delete-element`
**Type**: write · destructive · non-idempotent
**Input**: `{ post_id: int, element_id: string, force_delete?: bool, cache_scope?: string }`
**Output**: `{ success, post_id, element_id, removed: Element, message }`
**Errors**: standard + `element_not_found` + `force_delete_required`

### 1.10 `elementor/remove-element`
Safer alias for `delete-element` with `force_delete` defaulting to false and mandatory when element is populated.

### 1.11 `elementor/move-element`
**Type**: write · non-idempotent
**Input**: `{ post_id: int, element_id: string, new_parent_id: string|null, position: int }` (`new_parent_id=null` means root)
**Output**: `{ success, post_id, element_id, previous_parent_id, new_parent_id, new_position, message }`
**Errors**: standard + `element_not_found` + `descendant_destination`

### 1.12 `elementor/duplicate-element`
**Type**: write · non-idempotent
**Input**: `{ post_id: int, element_id: string }`
**Output**: `{ success, post_id, source_element_id, clone_element_id, message }` (clone has fresh IDs throughout its subtree)
**Errors**: standard + `element_not_found`

### 1.13 `elementor/reorder-elements`
**Type**: write · non-idempotent
**Input**: `{ post_id: int, parent_id: string|null, ordered_element_ids: array<string> }`
**Output**: `{ success, post_id, parent_id, new_order: array<string>, message }`
**Errors**: standard + `element_not_found`

### 1.14 `elementor/add-container`
**Type**: write · non-idempotent
**Input**: `{ post_id: int, parent_id?: string|null, position?: int, settings?: object, is_inner?: bool }`
**Output**: `{ success, post_id, element_id, element, message }`
**Errors**: standard

### 1.15 `elementor/add-widget`
**Type**: write · non-idempotent
**Input**: `{ post_id: int, widget_type: string, parent_id?: string|null, position?: int, settings?: object }`
**Output**: `{ success, post_id, element_id, element, message }`
**Errors**: standard + `invalid_widget_type`

### 1.16 `elementor/update-page-settings`
**Type**: write · destructive · non-idempotent
**Input**: `{ post_id: int, page_settings: object, force_replace?: bool }`
**Output**: `{ success, post_id, page_settings, message }`
**Errors**: standard

### 1.17 `elementor/create-page`
**Type**: write · non-idempotent
**Input**: `{ title: string, post_type?: 'page'|'post', status?: 'draft'|'publish', template?: string, page_settings?: object }`
**Output**: `{ success, post_id, edit_url, message }`
**Errors**: standard

---

## Group 2 — Widget shortcuts (5 abilities)

Thin wrappers over `add-widget` with type-specific input schemas.

### 2.1 `elementor/add-heading`
**Input**: `{ post_id: int, parent_id?: string, position?: int, title: string, header_size?: 'h1'..'h6', align?: string, color?: string }`
**Output**: `{ success, post_id, element_id, element, message }`

### 2.2 `elementor/add-text-editor`
**Input**: `{ post_id: int, parent_id?: string, position?: int, editor: string, align?: string }`
**Output**: same

### 2.3 `elementor/add-image`
**Input**: `{ post_id: int, parent_id?: string, position?: int, image: { id?: int, url?: string }, size?: string, align?: string, caption?: string, link?: object }`
**Output**: same

### 2.4 `elementor/add-button`
**Input**: `{ post_id: int, parent_id?: string, position?: int, text: string, link?: object, size?: string, align?: string }`
**Output**: same

### 2.5 `elementor/add-post-tabs`
**Input**: `{ post_id: int, parent_id?: string, position?: int, tabs: array<{title:string, taxonomy?:string, term_id?:int, query?:object}> }` (creates native Nested Tabs where each tab contains a Posts widget)
**Output**: same

---

## Group 3 — Discovery & guidance (6 abilities)

### 3.1 `elementor/get-widget-controls`
**Type**: read
**Input**: `{ widget_type: string, search?: string }`
**Output**: `{ success, widget_type, count, controls: array<Control>, message }`
**Errors**: `elementor_missing`, `invalid_widget_type`

### 3.2 `elementor/get-official-widget-catalog`
**Type**: read
**Input**: `{ category?: 'basic'|'pro'|'theme'|'woocommerce' }` (optional filter)
**Output**: `{ success, widgets: array<{name, title, category, tier}>, count, message }`
**Note**: uses cached fetch from Elementor.com; 12-hour transient.

### 3.3 `elementor/get-official-pattern-guidance`
**Type**: read
**Input**: `{ topic?: 'widgets'|'patterns'|'layouts' }` (optional)
**Output**: `{ success, guidance: object, source_policy: string, message }`

### 3.4 `elementor/get-theme-context`
**Type**: read
**Input**: `{}`
**Output**: `{ success, theme: {name, version, is_block_theme}, elementor: {version, pro_version?, container_experiment_active}, active_kit: {id, title}, viewport: object, guidance_basis: string, message }`

### 3.5 `elementor/get-style-guide`
**Type**: read
**Input**: `{}` or `{ kit_id?: int }`
**Output**: `{ success, colors: array, typography: array, buttons: object, forms: object, layout: object, guidance_basis: string, message }`

### 3.6 `elementor/evaluate-render-context`
**Type**: read
**Input**: `{ post_id: int }`
**Output**: `{ success, post_id, template: string, canvas_type: string, header_present: bool, footer_present: bool, wrapper_classes: array<string>, message }`

---

## Group 4 — Templates (11 abilities)

### 4.1 `elementor/list-templates`
**Input**: `{ template_type?: string, status?: 'publish'|'draft'|'trash'|'any', limit?: int, offset?: int }`
**Output**: `{ success, templates: array<Template>, count, message }`

### 4.2 `elementor/get-template`
**Input**: `{ template_id: int, include_data?: bool }`
**Output**: `{ success, template: Template, message }`

### 4.3 `elementor/create-template`
**Input**: `{ title: string, type: 'page'|'section'|'popup'|'header'|'footer'|'single'|'archive', status?: string, data?: array<Element>, conditions?: array<Condition>, popup_settings?: object }`
**Output**: `{ success, template_id, edit_url, message }`
**Errors**: standard + `invalid_template_type`

### 4.4 `elementor/update-template`
**Input**: `{ template_id: int, title?: string, data?: array<Element>, page_settings?: object, force_replace?: bool }`
**Output**: `{ success, template_id, message }`

### 4.5 `elementor/delete-template`
**Input**: `{ template_id: int, force?: bool }` (`force=true` → permanent delete, default trashes)
**Output**: `{ success, template_id, action: 'trashed'|'deleted', message }`

### 4.6 `elementor/restore-template`
**Input**: `{ template_id: int }`
**Output**: `{ success, template_id, message }`

### 4.7 `elementor/duplicate-template`
**Input**: `{ template_id: int, title?: string }`
**Output**: `{ success, source_template_id, duplicate_template_id, message }`

### 4.8 `elementor/empty-trash`
**Input**: `{ confirm: true }`
**Output**: `{ success, deleted_count, message }`
**Errors**: standard + refuses without `confirm=true`

### 4.9 `elementor/export-template`
**Input**: `{ template_id: int }`
**Output**: `{ success, template_id, data: object, message }` (JSON-encodable)

### 4.10 `elementor/import-template`
**Input**: `{ data: object, title?: string, overwrite_id?: int }`
**Output**: `{ success, template_id, message }`

### 4.11 `elementor/find-template-for-pattern`
**Input**: `{ pattern_keywords: string, template_type?: string, limit?: int }`
**Output**: `{ success, templates: array<{id, title, score, snippet}>, count, message }`

---

## Group 5 — Kits & site settings (7 abilities)

### 5.1 `elementor/list-kits`
**Input**: `{}`
**Output**: `{ success, kits: array<Kit>, active_kit_id, message }`

### 5.2 `elementor/get-kit-settings`
**Input**: `{ kit_id?: int }` (defaults to active)
**Output**: `{ success, kit_id, settings: object, message }`

### 5.3 `elementor/update-kit-settings`
**Input**: `{ kit_id?: int, settings: object, force_replace?: bool }`
**Output**: `{ success, kit_id, changed_keys: array<string>, message }`

### 5.4 `elementor/set-active-kit`
**Input**: `{ kit_id: int }`
**Output**: `{ success, previous_kit_id, active_kit_id, message }`

### 5.5 `elementor/list-global-widgets`
**Input**: `{}`
**Output**: `{ success, global_widgets: array, count, message }`

### 5.6 `elementor/list-experiments`
**Input**: `{}`
**Output**: `{ success, experiments: array<{name, state, default_state, description}>, message }`

### 5.7 `elementor/update-experiment`
**Input**: `{ experiment: string, state: 'active'|'inactive'|'default' }`
**Output**: `{ success, experiment, previous_state, new_state, message }`

---

## Group 6 — Theme Builder (2 abilities)

### 6.1 `elementor/get-theme-builder-conditions`
**Input**: `{ template_id: int }`
**Output**: `{ success, template_id, conditions: array<Condition>, message }`

### 6.2 `elementor/update-theme-builder-conditions`
**Input**: `{ template_id: int, conditions: array<Condition> }`
**Output**: `{ success, template_id, conditions, message }`

---

## Group 7 — System / maintenance (4 abilities)

### 7.1 `elementor/clear-cache`
**Input**: `{ scope?: 'post'|'site'|'all', post_id?: int, regenerate_css?: bool }`
**Output**: `{ success, scope, cleared: bool, css_regenerated?: bool, message }`

### 7.2 `elementor/replace-urls`
**Input**: `{ from: string, to: string, dry_run?: bool, post_types?: array<string> }`
**Output**: `{ success, replacements: int, posts_affected: int, dry_run: bool, message }`

### 7.3 `elementor/get-maintenance-mode`
**Input**: `{}`
**Output**: `{ success, enabled: bool, mode?: 'maintenance'|'coming_soon', template_id?: int, exclude_mode?: string, message }`

### 7.4 `elementor/update-maintenance-mode`
**Input**: `{ enabled: bool, mode?: string, template_id?: int, exclude_mode?: string }`
**Output**: `{ success, enabled, mode, template_id, message }`

---

## Group 8 — Design audits (28 abilities)

All share the shape: `{ success, post_id, findings: array<Finding>, recommendations: array<Recommendation>, score?: number, source_policy: string, guidance_basis: string, message }`.

**Aggregators**:
- `elementor/evaluate-design` — composes findings from every individual audit; returns aggregate score + issue list + recommendations
- `elementor/suggest-design-fixes` — turns aggregated findings into concrete fix suggestions
- `elementor/score-distinctiveness` — structural repetition score
- `elementor/extract-design-tokens` — recurring colors/typography/spacing tokens

**Individual audits** (14) — each `{ post_id: int, subtree_id?: string }` → findings/recommendations:
`audit-column-alignment-rhythm`, `audit-column-balance`, `audit-column-dominance`, `audit-column-necessity`, `audit-column-patterns`, `audit-composition-rhythm`, `audit-emphasis-drift`, `audit-generic-component-repetition`, `audit-generic-layout-patterns`, `audit-layout-mechanism-fit`, `audit-native-widget-opportunities`, `audit-section-rivalry`, `audit-separator-discipline`, `audit-surface-overuse`

**Normalise / fix / apply subtree operations** (7):
- `elementor/apply-text-hierarchy` — normalise heading/body/button typography in a subtree
- `elementor/enforce-boundary-coherence` — normalise a subtree to true full-width or coherent boxed boundaries
- `elementor/fix-visible-gap-rhythm` — remove hidden leading spacing that breaks visible gap rhythm
- `elementor/normalize-responsive-values` — fill tablet/mobile from desktop with capped spacing; inputs `{ post_id, subtree_id?, tablet_horizontal_spacing_cap?, mobile_horizontal_spacing_cap? }`
- `elementor/normalize-section-spacing-rhythm` — snap section padding and row gaps to a consistent rhythm
- `elementor/reset-negative-margins-subtree` — clamp negative margins in a subtree
- `elementor/zero-container-padding-subtree` — zero container padding in a subtree

**Copy / sync / convert helpers** (4):
- `elementor/copy-lane-settings` — copy width/gap lane settings between elements
- `elementor/copy-row-balance` — copy row rhythm + child balance between rows
- `elementor/image-widget-to-background-container` — convert an image-widget container into a native background-image container
- `elementor/sync-component-variant` — copy design-relevant settings from one subtree to another

All design-audit writes (apply/normalize/fix/copy/sync) go through `Document_Repository::save_data()` with `cache_scope` support and standard `force_replace` guarding.

---

## Group 9 — Elementor Pro Custom Code (5 abilities, Pro-gated)

### 9.1 `elementor/list-custom-code`
**Input**: `{ location?: 'head'|'body_start'|'body_end'|'footer', status?: string }`
**Output**: `{ success, snippets: array<Snippet>, count, message }`

### 9.2 `elementor/get-custom-code`
**Input**: `{ snippet_id: int }`
**Output**: `{ success, snippet: Snippet, message }`

### 9.3 `elementor/create-custom-code`
**Input**: `{ title: string, code: string, location: string, priority?: int, status?: 'publish'|'draft' }`
**Output**: `{ success, snippet_id, message }`

### 9.4 `elementor/update-custom-code`
**Input**: `{ snippet_id: int, title?: string, code?: string, location?: string, priority?: int, status?: string }`
**Output**: `{ success, snippet_id, message }`

### 9.5 `elementor/delete-custom-code`
**Input**: `{ snippet_id: int, force?: bool }`
**Output**: `{ success, snippet_id, action: 'trashed'|'deleted', message }`

**Errors on all**: standard + `elementor_pro_missing`

---

## Group 10 — Elementor Pro Form Submissions (3 abilities, Pro-gated)

### 10.1 `elementor/list-form-submissions`
**Input**: `{ form_id?: string, limit?: int, offset?: int, include_values?: bool }`
**Output**: `{ success, submissions: array<Submission>, count, message }`

### 10.2 `elementor/get-form-submission`
**Input**: `{ submission_id: int, include_values?: bool }`
**Output**: `{ success, submission: Submission, message }`

### 10.3 `elementor/delete-form-submission`
**Input**: `{ submission_id: int, confirm: true }`
**Output**: `{ success, submission_id, message }`

**Errors on all**: standard + `elementor_pro_missing`

---

## Summary

| Group | Count | Category | Gate |
|---|---|---|---|
| 1. Document/element ops | 17 | acrossai-abilities-manager-elementor | Elementor |
| 2. Widget shortcuts | 5 | " | Elementor |
| 3. Discovery & guidance | 6 | " | Elementor |
| 4. Templates | 11 | " | Elementor |
| 5. Kits & site settings | 7 | " | Elementor |
| 6. Theme Builder | 2 | " | Elementor |
| 7. System / maintenance | 4 | " | Elementor |
| 8. Design audits | 28 | " | Elementor |
| 9. Pro Custom Code | 5 | " | Elementor + Pro |
| 10. Pro Form Submissions | 3 | " | Elementor + Pro |
| **Total** | **88** | | |
