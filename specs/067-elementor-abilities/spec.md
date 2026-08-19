# Feature Specification: Elementor Ability Suite

**Feature Branch**: `067-elementor-abilities`
**Created**: 2026-08-13
**Status**: Draft
**Input**: User description: "Add 88 abilities that give clients complete read/write control over Elementor page-builder documents — schema discovery, raw document R/W, element-by-ID operations, template CRUD, kit/theme-builder management, cache control, design audits, and Elementor Pro custom-code + form-submission management. All abilities live under a new Elementor category with per-ability gates on Elementor being installed."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Discover a widget's schema before writing to it (Priority: P1)

A client authoring or updating an Elementor page needs to know what settings a given widget accepts before it can safely construct a payload. Rather than hard-coding widget schemas per widget type, the client requests the schema for any registered widget at runtime.

**Why this priority**: This single ability turns a per-widget-wrapper problem into a discover-then-write problem. Without it, every widget needs its own ability wrapper — with it, one generic `add-widget` / `update-element` pair can safely target any widget the site knows about.

**Independent Test**: On a site with Elementor installed and the free `nav-menu` widget registered, request the schema for `nav-menu`. Verify the response returns a structured list of the widget's control keys, types, and section groupings — enough for a client to construct a valid settings payload without prior knowledge.

**Acceptance Scenarios**:

1. **Given** a site with Elementor and the `heading` widget registered, **When** the client requests the schema for `heading`, **Then** the response returns success with a list of controls including `title`, `header_size`, `align`, and their expected types.
2. **Given** a widget type that is not registered on the current site, **When** the client requests its schema, **Then** the response returns success=false with an error identifying the unknown widget type — never a fatal error.
3. **Given** a widget with 100+ controls, **When** the client requests the schema with an optional search filter (e.g. "color"), **Then** the response returns only controls matching the filter.

---

### User Story 2 — Read a post's Elementor document tree (Priority: P1)

A client needs a structured view of the Elementor content stored on a post — the tree of containers/columns/widgets with their IDs, types, and settings — so it can locate specific elements to modify.

**Why this priority**: Every write against a specific element requires first reading the tree to find that element's canonical ID. Without a read primitive, clients cannot target anything.

**Independent Test**: Create an Elementor page with a container containing a heading and a paragraph. Request the document data for that page. Verify the response returns the full nested structure with unique element IDs for every node.

**Acceptance Scenarios**:

1. **Given** an Elementor page with nested containers and widgets, **When** the client requests the document data, **Then** the response returns the full element tree preserving nesting.
2. **Given** a post that has no Elementor content, **When** the client requests the document data, **Then** the response returns success with an empty tree.
3. **Given** a client that wants to locate an element by type or by inner text, **When** it queries the tree with a filter (widget type or contains-text), **Then** the response returns the matching elements with their paths from the root.

---

### User Story 3 — Modify a specific element by ID (Priority: P1)

A client needs to change a single element's settings (attributes, inner content) without disturbing the rest of the document.

**Why this priority**: Element-scoped updates are the safest write pattern — they minimise blast radius and prevent accidental wipes of other content. This is the workhorse of any AI-assisted authoring workflow.

**Independent Test**: Given a page with a heading element identified by ID `abc123`, invoke the update-element ability with that ID and new settings. Re-read the document and verify only that element changed and every other element is untouched.

**Acceptance Scenarios**:

1. **Given** a page with an element ID `abc123`, **When** the client updates it with new settings, **Then** the response returns success and re-reading the document confirms only that element changed.
2. **Given** the client wants a targeted merge (only patch a few settings, not replace the whole element), **When** it calls the merge-settings variant, **Then** the specified settings are merged into the existing element without disturbing unchanged keys.
3. **Given** a client attempts to replace a populated element with an empty payload, **When** no `force_replace=true` flag is provided, **Then** the response refuses the write and returns an error identifying the required flag.
4. **Given** an element ID that does not exist in the document, **When** the client attempts to update it, **Then** the response returns success=false with an "element not found" error.

---

### User Story 4 — Compose a page from scratch (Priority: P1)

A client needs to create a new Elementor page and add containers and widgets to it programmatically, producing a functional page ready to preview.

**Why this priority**: Without authoring primitives, clients can only edit pre-existing content. This unlocks generating new pages end-to-end.

**Independent Test**: Create a new Elementor page. Add a top-level container to it. Add a heading widget inside that container. Add a paragraph widget after the heading. Open the page in the Elementor editor — the three elements should render correctly with the intended nesting.

**Acceptance Scenarios**:

1. **Given** an empty page marked as Elementor, **When** the client adds a container followed by a widget inside that container, **Then** the resulting document tree shows the widget nested inside the container.
2. **Given** a client uses generic `add-widget` with a widget type and settings payload, **When** the widget type is registered, **Then** the widget is inserted with those settings.
3. **Given** a client attempts to add a widget of an unregistered type, **When** the ability runs, **Then** the response returns success=false with an "invalid widget type" error before any write occurs.

---

### User Story 5 — Reorganise elements within a document (Priority: P2)

A client needs to move, duplicate, remove, or reorder elements within an Elementor document without editing the underlying JSON by hand.

**Why this priority**: Structural mutations round out the authoring surface. Not blocking parity because clients can achieve similar results with read + update-data, but at higher risk.

**Independent Test**: Given a container with three child widgets, invoke move to relocate the second child to a different parent; invoke duplicate on the third; invoke remove on the duplicate. Re-read and verify the tree matches the expected shape at each step.

**Acceptance Scenarios**:

1. **Given** an element inside container A, **When** the client moves it to container B at position 0, **Then** re-reading shows the element as B's first child and absent from A.
2. **Given** an element containing nested children, **When** the client duplicates it, **Then** the duplicate has fresh unique element IDs throughout its subtree.
3. **Given** a client attempts to remove a top-level populated element, **When** no `force_delete=true` flag is provided, **Then** the response refuses the delete and returns an error identifying the required flag.
4. **Given** a client reorders three sibling children, **When** the ability runs with the new child-order array, **Then** re-reading shows the children in the new order.

---

### User Story 6 — Manage Elementor templates (Priority: P2)

A client needs full lifecycle control over saved Elementor templates: list, read, create, update, delete, restore, duplicate, empty-trash, export to JSON, import from JSON.

**Why this priority**: Templates are the reuse unit for Elementor design. Without template management, clients cannot cache reusable patterns or transfer designs across sites.

**Independent Test**: Create a page template with a name and a document payload. List templates — the new one appears. Read the template — the payload matches. Duplicate it — a copy appears with the same content. Delete the copy (moves to trash), list trashed templates — the copy is there. Restore it — it comes back to the active list.

**Acceptance Scenarios**:

1. **Given** the site has three saved templates, **When** the client lists templates, **Then** the response returns all three with their metadata (title, type, status).
2. **Given** the client wants to reuse a saved pattern before authoring raw containers, **When** it searches templates by pattern keywords, **Then** the response ranks matching templates for reuse.
3. **Given** a client wants to move a template between two sites, **When** it exports the template on site A and imports the returned JSON on site B, **Then** the template appears on site B with equivalent content.

---

### User Story 7 — Manage kits and site-wide Elementor settings (Priority: P2)

A client needs to inspect and modify the active Elementor Kit (global colors, typography, buttons, form fields, layout defaults), switch between kits, and manage related site settings (global widgets, experiments, maintenance mode).

**Why this priority**: Kit and site-settings management touches every page on the site simultaneously. It's a lower-frequency operation than page editing but has the highest impact when needed.

**Independent Test**: Get the current kit settings. Modify a global color. Re-read the kit — the color reflects the change. Every page that references that color token now shows the updated value on next render.

**Acceptance Scenarios**:

1. **Given** an active Elementor Kit, **When** the client requests kit settings, **Then** the response returns the current global-color palette, typography, and layout defaults.
2. **Given** the client updates a global colour value, **When** the update runs, **Then** subsequent reads reflect the change and Elementor's cache is invalidated.
3. **Given** two kits exist, **When** the client switches the active kit, **Then** subsequent kit reads target the newly-active kit.

---

### User Story 8 — Manage Theme Builder display conditions (Priority: P2)

A client needs to read and update the display conditions attached to a template (which pages/posts/archives use it as a Header, Footer, Single, Archive, etc.).

**Why this priority**: Theme Builder is Elementor Pro's core feature for building site-wide layouts. Managing conditions unlocks automated theme design.

**Independent Test**: Given a header template, request its display conditions. Update the conditions to target "all posts". Re-read — the new conditions are present. Load a post on the frontend and verify the header renders.

**Acceptance Scenarios**:

1. **Given** a template with conditions targeting a specific page ID, **When** the client requests the conditions, **Then** the response returns the current condition list.
2. **Given** a client updates conditions to target a taxonomy term, **When** the update runs, **Then** subsequent reads reflect the new conditions and Elementor's condition cache is invalidated.

---

### User Story 9 — Audit and improve page design quality (Priority: P3)

A client wants automated feedback on an Elementor page's design quality — column balance, composition rhythm, emphasis distribution, generic-layout repetition — and actionable fix suggestions.

**Why this priority**: Design audits are a value-add for teams that want AI-assisted design review. Not required for basic authoring workflows.

**Independent Test**: Given a page with a 50/50/50/50/50/50 repetition of equal columns, invoke the "evaluate design" aggregator. Verify the response identifies the column-monotony issue and returns a "suggest fixes" recommendation to break the pattern (e.g. Grid layout, asymmetric split).

**Acceptance Scenarios**:

1. **Given** a page with repeated equal-column rows, **When** the client runs the evaluate-design aggregator, **Then** the response returns a design score plus a list of specific issues and fix recommendations.
2. **Given** the client runs an individual audit (e.g. column-balance), **When** the audit runs, **Then** the response returns a narrow, targeted finding on that dimension without the full aggregator overhead.
3. **Given** a client wants to normalise responsive values across breakpoints, **When** it invokes the normalise-responsive-values ability, **Then** tablet and mobile values are populated from desktop with capped spacing that respects narrow breakpoints.

---

### User Story 10 — Manage Elementor Pro Custom Code and Form Submissions (Priority: P3)

A client on a site with Elementor Pro installed needs to manage Elementor's Custom Code snippets (site-wide code injection) and inspect/delete Form widget submissions.

**Why this priority**: Pro-only features gated on Elementor Pro being installed. Valuable for full-site automation but only usable on Pro sites.

**Independent Test**: On a site with Elementor Pro installed, create a custom code snippet with a title and script content. List custom code — the new snippet appears. Delete it — it's gone.

**Acceptance Scenarios**:

1. **Given** a site with Elementor Pro installed, **When** the client creates a custom code snippet, **Then** the snippet is registered with the specified title, code, and status.
2. **Given** a site with Elementor Pro NOT installed, **When** the client attempts to invoke any Pro-only ability, **Then** the ability is not registered at all — it does not appear in the ability library.
3. **Given** a site with active form submissions, **When** the client lists submissions filtered by form ID, **Then** the response returns the matching submissions with metadata.

---

### Edge Cases

- **Elementor not installed**: none of the 80 free-Elementor abilities appear in the ability library; the category is not registered; the plugin loads cleanly with no errors.
- **Elementor deactivated mid-session**: an in-flight ability invocation must return `{ success: false, error_code: "elementor_missing" }` cleanly, never a fatal error.
- **Elementor Pro not installed**: the 8 Pro-only abilities do not register; the 80 free abilities register normally.
- **Elementor Pro deactivated mid-session**: an in-flight Pro-ability invocation returns `{ success: false, error_code: "elementor_pro_missing" }`.
- **Post type not compatible with Elementor**: attempts to invoke an ability against a non-editable post type (revision / nav_menu_item / custom_css / customize_changeset / oembed_cache / user_request) refuse with a clear error.
- **Populated document, incomplete replacement**: `update-data` without `force_replace=true` refuses to write when the new payload is materially smaller than the existing document — prevents accidental wipes.
- **Populated element, delete without force**: top-level element deletion or deletion of any element with child content refuses without `force_delete=true`.
- **Move to descendant**: attempting to move an element into its own subtree is refused with a clear error (would create a cycle).
- **Unregistered widget type**: `add-widget` with a widget type not present on the site refuses before any write.
- **Concurrent edits**: two clients editing the same document simultaneously — last write wins; no built-in optimistic locking. Matches existing WordPress editing behaviour.
- **Corrupt `_elementor_data`**: if a document's stored JSON is malformed, read abilities return a structured error identifying the corruption; `update-data` with `force_replace=true` can repair by overwriting.
- **Deep nesting**: documents with 10+ nesting levels are handled without performance degradation.

## Requirements *(mandatory)*

### Functional Requirements

**Registration and gating**

- **FR-001**: All 88 new abilities MUST live under a new category with the slug `acrossai-abilities-manager-elementor`.
- **FR-002**: All ability slugs MUST use the prefix `elementor/` (e.g. `elementor/get-widget-controls`) to disambiguate from existing content abilities.
- **FR-003**: All 88 abilities MUST be gated on the presence of Elementor. When Elementor is not installed, none of the abilities register and the category is not advertised.
- **FR-004**: 8 abilities (5 Custom Code + 3 Form Submissions) MUST additionally be gated on Elementor Pro being installed.
- **FR-005**: The gating check MUST happen at both registration time (bootstrap) and execution time (per-ability defense-in-depth). Runtime deactivation of Elementor must not cause fatal errors.

**Document / element operations (17 abilities)**

- **FR-006**: The system MUST provide abilities to read (`get-data`), replace (`update-data`), find-and-replace within (`patch-data`), and clone across documents (`clone-data`) the raw Elementor document data of a post.
- **FR-007**: `update-data` and `clone-data` MUST refuse destructive writes against populated documents unless the client passes `force_replace=true`.
- **FR-008**: The system MUST provide abilities to locate a single element by ID (`get-element`), search for elements by type or contains-text (`find-elements`), and update a single element's settings (`update-element`, `merge-element-settings`).
- **FR-009**: `merge-element-settings` MUST perform a deep-merge on the target element's settings only, without disturbing sibling elements or unchanged settings.
- **FR-010**: The system MUST provide abilities to delete an element (`delete-element` with force_delete guard, `remove-element` as a safer alias), move an element to a new parent/position (`move-element`), duplicate an element with fresh IDs throughout its subtree (`duplicate-element`), and reorder direct children of a parent (`reorder-elements`).
- **FR-011**: `move-element` MUST refuse moves whose destination lies inside the source element's own subtree.
- **FR-012**: The system MUST provide abilities to add a top-level or nested container (`add-container`), add any registered widget by type with a settings payload (`add-widget`), update the page-level settings of an Elementor document (`update-page-settings`), and create a new post/page pre-configured for Elementor authoring (`create-page`).

**Widget shortcuts (5 abilities)**

- **FR-013**: The system MUST provide type-specific convenience wrappers over `add-widget` for the five most-authored widget types: heading, text-editor, image, button, and post-tabs (native Nested Tabs). Each wrapper accepts a targeted schema for that widget type.

**Discovery and guidance (6 abilities)**

- **FR-014**: The system MUST provide an ability (`get-widget-controls`) that returns the schema of any registered Elementor widget type on the current site, with optional filtering by control name/label.
- **FR-015**: The system MUST provide an ability (`get-official-widget-catalog`) that returns Elementor's canonical widget catalog grouped by Basic / Pro / Theme / WooCommerce categories.
- **FR-016**: The system MUST provide an ability (`get-official-pattern-guidance`) that returns the canonical Elementor.com pattern & widget guidance catalog used by design audits.
- **FR-017**: The system MUST provide abilities that summarise the active theme + Elementor version + active kit + viewport settings (`get-theme-context`), build a style-guide summary from the active kit (`get-style-guide`), and inspect the frontend render context separately from Elementor content quality (`evaluate-render-context`).

**Templates (11 abilities)**

- **FR-018**: The system MUST provide full CRUD on Elementor templates: list, get, create, update, delete (to trash), restore (from trash), duplicate, empty-trash (permanent).
- **FR-019**: The system MUST support template types: page, section, popup, header, footer, single, archive, and any Elementor-registered template subtype.
- **FR-020**: The system MUST provide export-to-JSON and import-from-JSON abilities for template portability across sites.
- **FR-021**: The system MUST provide a pattern-search ability (`find-template-for-pattern`) that returns the closest saved template matching a supplied pattern keyword before falling back to raw container authoring.

**Kits and site settings (7 abilities)**

- **FR-022**: The system MUST provide abilities to list available kits, get and update the active kit's settings, and switch the active kit.
- **FR-023**: The system MUST provide an ability to list all global (reusable) widgets registered on the site.
- **FR-024**: The system MUST provide abilities to list and update Elementor experiments (feature flags).

**Theme Builder (2 abilities)**

- **FR-025**: The system MUST provide abilities to get and update display conditions attached to a Theme Builder template.

**System / maintenance (4 abilities)**

- **FR-026**: The system MUST provide an ability to clear Elementor's cache at either post-scope or site-scope, with an optional `regenerate_css` flag to regenerate a specific post's CSS file.
- **FR-027**: The system MUST provide an ability to bulk-replace URLs inside stored Elementor document data (post-migration URL rewrites).
- **FR-028**: The system MUST provide abilities to get and update Elementor's maintenance-mode settings.

**Design audits (28 abilities)**

- **FR-029**: The system MUST provide an aggregate `evaluate-design` ability that composes results from every individual audit into a single design score plus issue list.
- **FR-030**: The system MUST provide a `suggest-design-fixes` ability that turns aggregated evaluation results into concrete fix recommendations.
- **FR-031**: The system MUST provide 14 individual audit abilities each targeting one dimension: column-alignment-rhythm, column-balance, column-dominance, column-necessity, column-patterns, composition-rhythm, emphasis-drift, generic-component-repetition, generic-layout-patterns, layout-mechanism-fit, native-widget-opportunities, section-rivalry, separator-discipline, surface-overuse.
- **FR-032**: The system MUST provide auxiliary audit + design abilities: score-distinctiveness (structural repetition score), extract-design-tokens (recurring colors/type/spacing), apply-text-hierarchy (normalise heading/body typography), enforce-boundary-coherence (normalise subtree edges), fix-visible-gap-rhythm (remove hidden leading spacing), normalize-responsive-values (fill breakpoints with capped spacing), normalize-section-spacing-rhythm (snap to rhythm), reset-negative-margins-subtree (clamp negatives), zero-container-padding-subtree (zero out padding), copy-lane-settings + copy-row-balance + image-widget-to-background-container + sync-component-variant (structural copy/convert helpers).

**Elementor Pro — Custom Code (5 abilities)**

- **FR-033**: On sites with Elementor Pro, the system MUST provide full CRUD on Elementor Custom Code snippets (list, get, create, update, delete) with title, code, location, priority, and status fields.

**Elementor Pro — Form Submissions (3 abilities)**

- **FR-034**: On sites with Elementor Pro, the system MUST provide list, get, and delete operations on Elementor Form widget submissions with optional filters by form ID.

**Cross-cutting**

- **FR-035**: Every ability MUST return a response envelope of shape `{ success, post_id?, <payload_key>, message, error_code? }` matching the existing AcrossAI content-ability convention.
- **FR-036**: Every write ability MUST enforce the same capability model as existing content abilities: `manage_options` AND `edit_posts` globally, plus `edit_post($post_id)` per-post.
- **FR-037**: Every write ability MUST refuse to operate on internal-only post types (revision, nav_menu_item, custom_css, customize_changeset, oembed_cache, user_request).
- **FR-038**: All writes to `_elementor_data` MUST go through `wp_slash()` before `update_post_meta()` to preserve JSON escape sequences.
- **FR-039**: All writes MUST invalidate Elementor's per-post CSS cache and rebuild it on next render.
- **FR-040**: All destructive writes against populated content MUST require an explicit `force_replace=true` or `force_delete=true` flag.

### Key Entities

- **Elementor Document**: A post carrying Elementor content in `_elementor_data` post meta. Identified by post ID. Contains a tree of Elements.
- **Element**: A single node in a Document's tree. Has a unique 7-character hex ID, an element type (`container` / `widget` / legacy `section` / legacy `column`), a widget-type name (for widget elements), a settings object, and an ordered list of child Elements.
- **Widget Type**: A registered Elementor widget class. Identified by name (e.g. `heading`, `nav-menu`, `posts`). Exposes a controls schema at runtime via Elementor's widgets manager.
- **Elementor Template**: A post of type `elementor_library` with a template type (page / section / popup / header / footer / single / archive) and optional display conditions.
- **Elementor Kit**: A special `elementor_library` post that stores site-wide design tokens (global colors, typography, buttons, form defaults, layout defaults). One kit is active at a time.
- **Theme Builder Condition**: A display rule attached to a template that decides which pages/posts/archives the template renders on.
- **Elementor Custom Code Snippet** (Pro): A `elementor_snippet` CPT post storing code content, location (head/body-start/body-end/footer), priority, and status.
- **Elementor Form Submission** (Pro): A record of a submitted Elementor Form, stored by Pro in its own tables. Identified by submission ID and form ID.
- **Response Envelope**: Standard shape returned by every ability — `{ success, post_id?, <payload>, message, error_code? }`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a site with Elementor installed, all 80 free-Elementor abilities appear in the ability library and are invocable. Without Elementor, none appear.
- **SC-002**: On a site with Elementor Pro installed, all 88 abilities appear. Without Pro but with free Elementor, only the 80 free abilities appear.
- **SC-003**: Elementor deactivation mid-session does NOT produce a fatal error — every ability's next invocation returns a clean `{ success: false, error_code: "elementor_missing" }` envelope.
- **SC-004**: A client can discover any registered Elementor widget's schema in a single call (`get-widget-controls`) — no per-widget wrapper needed.
- **SC-005**: A client can build a functional Elementor page from scratch (create page, add container, add 3 widgets) in no more than 5 ability calls.
- **SC-006**: A client can perform any element operation (get, find, update, merge, delete, move, duplicate, reorder) by targeting an element ID — no operation requires more than one call to affect one element.
- **SC-007**: Every destructive write against a populated document without `force_replace=true` / `force_delete=true` is refused with a clear error before any state change — 0 partial writes.
- **SC-008**: A full round-trip of read → write-unchanged preserves `_elementor_data` byte-for-byte in at least 95% of realistic test documents (parser normalisation edge cases excluded).
- **SC-009**: Design-audit aggregators (`evaluate-design`, `suggest-design-fixes`) complete in under 3 seconds on a page with up to 100 elements.
- **SC-010**: The 577-test baseline PHPUnit suite continues to pass unchanged after this feature merges; the plugin adds at least 88 new source-inspection tests plus utility tests.

## Assumptions

- Clients invoke abilities via the WordPress Abilities API — no new transport is introduced.
- Sites either have Elementor installed (all 80 free abilities available) or do not (none available). Sites additionally may have Elementor Pro installed (unlocking the 8 Pro-only abilities).
- Elementor's runtime widget-registry (`WP_Block_Type_Registry` equivalent for Elementor widgets) accurately reflects the widgets available on the current site.
- Third-party Elementor extensions (Jet Engine, Crocoblock, etc.) that register their own widgets are automatically discoverable via `get-widget-controls` — no special-casing needed.
- Concurrent-write safety is out of scope; last-write-wins matches existing WordPress editing behaviour.
- No UI changes are required — abilities are consumed by clients over the Abilities API and appear in the existing Library UI without new admin surfaces.
- Design-audit outputs are opinionated heuristics grounded in Elementor's own official pattern guidance; different reviewers may weight the findings differently.
- Kit-level operations may temporarily desync Elementor's on-disk CSS cache; the cache-clear ability restores consistency.
- Custom-code (Pro) writes create/update posts of Elementor Pro's `elementor_snippet` CPT — the ability layer assumes Pro's CPT registration is present when the Pro gate passes.
- Form-submission (Pro) reads and deletes target Elementor Pro's own data storage — availability depends on Pro's DB tables existing.
