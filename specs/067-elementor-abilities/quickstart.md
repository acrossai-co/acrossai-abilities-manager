# Quickstart: Elementor Ability Suite

**Feature**: 067-elementor-abilities
**Date**: 2026-08-13

End-to-end walkthrough exercising the 10 ability groups on a live Elementor site. Use as the verification script referenced by plan.md's Verification section.

## Setup

Ensure the site has:
- WordPress 6.9+
- Elementor 3.x+ active
- Optionally Elementor Pro active (for Group 9 + 10)
- A user with `manage_options` + `edit_posts` capabilities logged in

Confirm ability visibility:

```
wp ability list --category=acrossai-abilities-manager-elementor
```
Expected: 80 abilities listed (or 88 if Pro active). None if Elementor is not installed.

---

## Step 1 — Discover a widget's schema (Group 3)

```json
Ability: acrossai/elementor-get-widget-controls
Input:   { "widget_type": "heading" }
```

**Expected**: `success:true`, `count > 0`, `controls` array includes at least `title`, `header_size`, `align`, `title_color`. Repeat with `widget_type: "nav-menu"` and `widget_type: "posts"`.

---

## Step 2 — Create an Elementor page (Group 1)

```json
Ability: acrossai/elementor-create-page
Input:   { "title": "067 Demo Page", "post_type": "page", "status": "draft", "template": "elementor_canvas" }
```

**Expected**: `success:true`, `post_id: 999`, `edit_url` present. Note the post ID — used below as `POST_ID`.

---

## Step 3 — Add a container (Group 1)

```json
Ability: acrossai/elementor-add-container
Input:   { "post_id": POST_ID, "settings": { "content_width": "boxed", "gap": { "size": 20, "unit": "px" } } }
```

**Expected**: `success:true`, `element_id: "a1b2c3d"` (7-char hex). Note as `CONTAINER_ID`.

---

## Step 4 — Add three widgets inside the container (Group 1 + Group 2)

```json
Ability: acrossai/elementor-add-heading
Input:   { "post_id": POST_ID, "parent_id": "CONTAINER_ID", "title": "Hello Elementor", "header_size": "h1" }
```
→ `element_id: "b2c3d4e"` (HEADING_ID)

```json
Ability: acrossai/elementor-add-text-editor
Input:   { "post_id": POST_ID, "parent_id": "CONTAINER_ID", "editor": "<p>Body copy for the demo.</p>", "align": "left" }
```
→ `element_id: "c3d4e5f"` (TEXT_ID)

```json
Ability: acrossai/elementor-add-button
Input:   { "post_id": POST_ID, "parent_id": "CONTAINER_ID", "text": "Learn more", "link": { "url": "https://example.com", "is_external": true }, "align": "center" }
```
→ `element_id: "d4e5f6a"` (BUTTON_ID)

**SC-005 checkpoint**: page created + container + 3 widgets = 5 ability calls total (create + container + heading + text + button). ✓

---

## Step 5 — Read the document back (Group 1)

```json
Ability: acrossai/elementor-get-data
Input:   { "post_id": POST_ID }
```

**Expected**: `success:true`, `data` array contains one container element whose `elements` array contains the three widgets in insertion order. Each element has a 7-char hex `id`.

---

## Step 6 — Find elements by widget type (Group 1)

```json
Ability: acrossai/elementor-find-elements
Input:   { "post_id": POST_ID, "widget_type": "heading", "include_path": true }
```

**Expected**: `success:true`, `elements` contains one entry with `id: "HEADING_ID"` and `path: ["CONTAINER_ID"]`.

---

## Step 7 — Merge settings into an element (Group 1)

```json
Ability: acrossai/elementor-merge-element-settings
Input:   { "post_id": POST_ID, "element_id": "HEADING_ID", "settings": { "align": "center", "title_color": "#ff5722" } }
```

**Expected**: `success:true`, `changed_keys` includes `align` and `title_color`. Re-reading the element shows the new values. Other widgets in the container unchanged.

---

## Step 8 — Update an element (Group 1, guarded)

```json
Ability: acrossai/elementor-update-element
Input:   { "post_id": POST_ID, "element_id": "TEXT_ID", "element": { "widgetType": "text-editor", "settings": { "editor": "<p>Updated body copy.</p>" } } }
```

**Expected**: `success:true`. Attempt the same update but replace `settings` with `{}` — expect `error_code: force_replace_required` (unless the payload is trivially smaller than existing).

---

## Step 9 — Duplicate an element (Group 1)

```json
Ability: acrossai/elementor-duplicate-element
Input:   { "post_id": POST_ID, "element_id": "BUTTON_ID" }
```

**Expected**: `success:true`, `clone_element_id` differs from `BUTTON_ID`. Re-reading shows two button widgets at end of container with distinct IDs.

---

## Step 10 — Move an element (Group 1)

```json
Ability: acrossai/elementor-move-element
Input:   { "post_id": POST_ID, "element_id": "HEADING_ID", "new_parent_id": null, "position": 0 }
```

**Expected**: `success:true`. Heading is now a top-level element at index 0; container drops to index 1. Attempt `new_parent_id: HEADING_ID` (destination inside source) — expect `error_code: descendant_destination`.

---

## Step 11 — Reorder + Delete (Group 1)

```json
Ability: acrossai/elementor-reorder-elements
Input:   { "post_id": POST_ID, "parent_id": "CONTAINER_ID", "ordered_element_ids": ["BUTTON_ID", "TEXT_ID", "d4e5f6a"] }
```

Then delete the duplicate button:

```json
Ability: acrossai/elementor-delete-element
Input:   { "post_id": POST_ID, "element_id": "d4e5f6a" }
```

**Expected**: both succeed. Deleting a populated top-level element without `force_delete:true` → `error_code: force_delete_required`.

---

## Step 12 — Templates (Group 4)

Save the current design as a template:

```json
Ability: acrossai/elementor-create-template
Input:   { "title": "067 Demo Template", "type": "page", "status": "publish", "data": [/* container tree from Step 5 */] }
```

Then list and get:

```json
Ability: acrossai/elementor-list-templates    Input: { "template_type": "page" }
Ability: acrossai/elementor-get-template      Input: { "template_id": TEMPLATE_ID, "include_data": true }
Ability: acrossai/elementor-duplicate-template Input: { "template_id": TEMPLATE_ID, "title": "Copy of 067 Demo" }
```

Trash and restore:

```json
Ability: acrossai/elementor-delete-template   Input: { "template_id": TEMPLATE_ID }
Ability: acrossai/elementor-restore-template  Input: { "template_id": TEMPLATE_ID }
```

Export / import round-trip:

```json
Ability: acrossai/elementor-export-template   Input: { "template_id": TEMPLATE_ID }
Ability: acrossai/elementor-import-template   Input: { "data": <exported_json>, "title": "Reimported 067 Demo" }
```

---

## Step 13 — Kit & site settings (Group 5)

```json
Ability: acrossai/elementor-list-kits            Input: {}
Ability: acrossai/elementor-get-kit-settings     Input: {}
Ability: acrossai/elementor-update-kit-settings  Input: { "settings": { "system_colors": [{"_id":"primary","title":"Primary","color":"#00695c"}, /* ... */] } }
```

**Expected**: kit reads show the new palette. Frontend renders using the new color token on next request.

---

## Step 14 — Theme Builder conditions (Group 6, requires template of type header/footer/single/archive)

```json
Ability: acrossai/elementor-get-theme-builder-conditions   Input: { "template_id": HEADER_ID }
Ability: acrossai/elementor-update-theme-builder-conditions Input: { "template_id": HEADER_ID, "conditions": [{"type":"include","name":"general"}] }
```

**Expected**: subsequent reads reflect new conditions; frontend renders the template on matching pages.

---

## Step 15 — System / cache (Group 7)

```json
Ability: acrossai/elementor-clear-cache           Input: { "scope": "post", "post_id": POST_ID, "regenerate_css": true }
Ability: acrossai/elementor-clear-cache           Input: { "scope": "site" }
Ability: acrossai/elementor-replace-urls          Input: { "from": "http://old.example.com", "to": "https://new.example.com", "dry_run": true }
Ability: acrossai/elementor-get-maintenance-mode  Input: {}
Ability: acrossai/elementor-update-maintenance-mode Input: { "enabled": false }
```

---

## Step 16 — Design audits (Group 8)

Aggregate:

```json
Ability: acrossai/elementor-evaluate-design        Input: { "post_id": POST_ID }
Ability: acrossai/elementor-suggest-design-fixes   Input: { "post_id": POST_ID }
```

**SC-009 checkpoint**: response returns within 3 seconds on the small demo page. On a larger fixture page (100+ elements), verify still <3s.

Individual audits (spot check 2-3):

```json
Ability: acrossai/elementor-audit-column-balance         Input: { "post_id": POST_ID }
Ability: acrossai/elementor-audit-native-widget-opportunities Input: { "post_id": POST_ID }
Ability: acrossai/elementor-normalize-responsive-values  Input: { "post_id": POST_ID, "tablet_horizontal_spacing_cap": 20 }
```

---

## Step 17 — Elementor Pro (Group 9 + 10, requires Pro active)

Custom Code CRUD:

```json
Ability: acrossai/elementor-create-custom-code  Input: { "title": "GA snippet", "code": "<script>console.log('ga');</script>", "location": "head", "status": "publish" }
Ability: acrossai/elementor-list-custom-code    Input: {}
Ability: acrossai/elementor-update-custom-code  Input: { "snippet_id": SNIPPET_ID, "priority": 20 }
Ability: acrossai/elementor-delete-custom-code  Input: { "snippet_id": SNIPPET_ID }
```

Form submissions:

```json
Ability: acrossai/elementor-list-form-submissions   Input: { "form_id": "contact", "limit": 25, "include_values": true }
Ability: acrossai/elementor-get-form-submission     Input: { "submission_id": SUBMISSION_ID, "include_values": true }
Ability: acrossai/elementor-delete-form-submission  Input: { "submission_id": SUBMISSION_ID, "confirm": true }
```

---

## Absence-gate verification

**Elementor deactivated**:

```
wp plugin deactivate elementor
wp ability list --category=acrossai-abilities-manager-elementor
```
Expected: 0 abilities listed. Plugin loads without errors.

Reactivate:
```
wp plugin activate elementor
wp ability list --category=acrossai-abilities-manager-elementor
```
Expected: 80 (or 88 with Pro) abilities listed.

**Elementor Pro deactivated**:

```
wp plugin deactivate elementor-pro
wp ability list --category=acrossai-abilities-manager-elementor | grep -c "custom-code\|form-submission"
```
Expected: 0. Reactivate Pro → 8 Pro abilities reappear.

**Mid-session deactivation**:

Start a WP-CLI session, invoke `acrossai/elementor-get-widget-controls` — succeeds. Deactivate Elementor in another terminal. Invoke again — expect `success:false, error_code:elementor_missing`, no fatal error.

---

## Failure paths worth exercising

| Scenario | Expected `error_code` |
|---|---|
| Any ability called on non-Elementor site | `elementor_missing` |
| Any Pro ability called without Pro active | `elementor_pro_missing` |
| `get-widget-controls` with unknown widget_type | `invalid_widget_type` |
| Any ability against `post_id` for a revision | `post_type_forbidden` |
| `update-data` on populated document without `force_replace:true` | `force_replace_required` |
| `delete-element` on top-level element without `force_delete:true` | `force_delete_required` |
| `move-element` with destination inside source subtree | `descendant_destination` |
| Any ability called by a subscriber user | `insufficient_capability` |
| `get-element` with unknown element_id | `element_not_found` |
