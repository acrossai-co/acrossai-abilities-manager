# Planning: Library Tab Group (Feature 037)

Add page-level tab navigation to the Library admin page
(`wp-admin/admin.php?page=acrossai-abilities-library`) so add-on authors can
bucket their abilities under a tab. The mechanism mirrors the existing
display-only `sub_group` field but operates one layer up — at the tab bar
rather than the in-card sub-heading. Registration is via a new optional
`args['tab_group']` key on the existing `Ability_Definition` subclass return
value; no new filter, no new class, no new persisted config.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "037-library-tab-group"

# 2. Specify
/speckit.specify "Add a tab bar to the Library admin page driven by a new optional 'tab_group' field on Ability_Definition return values.

What needs to be done:
(1) Extend Ability_Definition::push_definition() to read args['tab_group'] alongside the existing sub_group extraction and copy it to the top-level definition row when non-empty.
(2) Add 'tab_group' to AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS and ::OPTIONAL_FIELDS, and sanitize+pass it through validate_and_normalize() the same way sub_group is handled.
(3) In src/js/ability-library/components/LibraryPage.js, thread tab_group through groupDefinitions() onto each slug record, then render a tab bar above the card list. Tabs: a built-in 'All' tab plus one tab per unique tab_group. When a non-All tab is active, filter each card's slugs to those matching the active tab_group and hide cards with no matching slugs. The 'All' tab shows every ability. Hide the tab bar entirely when no add-on declared a tab_group.
(4) tab_group doubles as both slug and display text — title-cased on the JS side via the same ucwords/'-'→' ' rule used for category_label on the PHP side. No tab_group_label field.
(5) tab_group is display-only — never persisted into saved config, never affects execution or REST.
(6) Add SCSS for the tab bar in src/scss/ability-library/admin.scss (minimal, wp-admin native feel).
(7) Extend the existing Jest test file for groupDefinitions() to assert tab_group is threaded through. Extend the existing PHP unit test that covers sub_group pass-through to also cover tab_group with the same shape — present → carried through, missing → absent, bogus characters → sanitized via the existing sanitize_key_field() helper.

Files to edit:
- includes/Modules/Library/Ability_Definition.php
- includes/Modules/Library/AcrossAI_Ability_Library_Registry.php
- src/js/ability-library/components/LibraryPage.js
- src/scss/ability-library/admin.scss
- existing Jest test file covering groupDefinitions()
- existing PHP test file covering Registry sub_group pass-through

Discussion recap (clarifications confirmed with the user):
- Q: When a user clicks a tab, what should they see? A: A new optional 'tab_group' field on the ability(), parallel to sub_group. All abilities sharing the same tab_group value appear inside that tab.
- Q: What happens to abilities without tab_group? A: A built-in 'All' tab always exists and shows every ability — abilities tagged with a tab_group appear in both 'All' and their group tab. Abilities without tab_group only appear in 'All'.
- Q: Should tab_group have a paired tab_group_label like sub_group_label? A: No. tab_group is a single field that doubles as slug and display label (title-cased).
- Q: How should the tab UI render? A: React tabs inside the existing LibraryPage SPA — no native WordPress nav-tab-wrapper, no separate URLs per tab."
```

---

## Scope Rules

### Ignore from this feature

- Persisting the active tab across reloads (no localStorage, no REST config field).
- Any per-tab custom render callback or filter — registration is solely via `args['tab_group']`.
- A separate `tab_group_label` field — the user explicitly declined; `tab_group` doubles as slug and display.
- Filter-based tab contribution from sources other than `Ability_Definition` subclasses. The existing `acrossai_abilities_api_init` filter remains the single collection point; this feature adds no new hooks.
- Saved-config schema changes. The on-disk shape (`{ enabled, mode, sub_keys }` per category) is unchanged.
- REST API changes. The `/acrossai-abilities-library/v1/abilities/config` endpoints are unchanged.

### Must do

- Add `tab_group` extraction and pass-through end-to-end (PHP base class → Registry → JS data → React UI).
- Render a tab bar above the existing category cards, with a built-in **All** tab plus one tab per unique `tab_group`.
- Hide the tab bar when no `tab_group` is declared (the page renders identically to today).
- Title-case the displayed tab label using the same `ucwords(str_replace('-', ' ', …))` rule the PHP side already uses for `category_label`.

---

## Discussion Recap

The clarifications captured in the planning session:

| Question | Decision |
|---|---|
| What goes in each tab? | A new optional `args['tab_group']` on the `ability()` return value. Every ability sharing that value appears under that tab. Mirrors the existing `sub_group` pattern, one layer up. |
| Default-tab behaviour | A built-in **All** tab always exists and shows every ability. Abilities with `tab_group` also appear in their group's tab. Abilities without `tab_group` appear only in **All**. |
| Paired `tab_group_label`? | No. Single `tab_group` field doubles as slug and (title-cased) display text. |
| Tab UI rendering | React tabs inside the existing `LibraryPage` SPA. No native `nav-tab-wrapper`, no per-tab URL. Active-tab state is component state. |

---

## Implementation Points

### Point 1 — PHP base class extracts `tab_group`

**File**: `includes/Modules/Library/Ability_Definition.php`

In `push_definition()`, alongside the existing `$sub_group` extraction:

- Read `$args['tab_group']` as a string (empty if missing).
- When non-empty, set `$row['tab_group'] = $tab_group;`.
- Do **not** add a `tab_group_label` row key.

Update the class docblock so the optional-args list reads "`args['sub_group']`, `args['tab_group']` — display-only".

### Point 2 — Registry accepts and sanitizes `tab_group`

**File**: `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`

- Add `'tab_group'` to `ALLOWED_ARGS_FIELDS` so it survives the `array_intersect_key` strip.
- Add `'tab_group'` to `OPTIONAL_FIELDS` for parity with `sub_group`.
- In `validate_and_normalize()`, immediately after the existing `sub_group` block, add a `tab_group` pass-through that calls `AcrossAI_Ability_Library_Config::sanitize_key_field()` and, when non-empty, sets `$entry['tab_group']`.
- Update the inline docblock above `apply_filters( 'acrossai_abilities_api_init', … )` to mention `tab_group` next to `sub_group`.

Reuse `AcrossAI_Ability_Library_Config::sanitize_key_field()` — the same helper already handles `category`, `slug`, and `sub_group`.

### Point 3 — Thread `tab_group` through `groupDefinitions()`

**File**: `src/js/ability-library/components/LibraryPage.js`

- Destructure `tab_group: tabGroup` from each definition inside `groupDefinitions()` alongside the existing `sub_group` destructure.
- Push `tabGroup: tabGroup || ''` into each slug record.
- Do not change card grouping — cards remain grouped by `category`.

`groupDefinitions` is a named export (per `PATTERN-NAMED-EXPORT-JEST`) so the change is unit-testable without rendering.

### Point 4 — Add tab bar and filter logic

**File**: `src/js/ability-library/components/LibraryPage.js`

- Compute the sorted set of unique `tabGroup` values across all slugs.
- Build the tab list as `[{ slug: '__all__', label: 'All' }, ...uniqueGroups]`. Title-case each group label by replacing `-` with space and running a `ucwords`-equivalent in JS, to match the PHP `ucwords(str_replace('-', ' ', …))` rule used elsewhere in the module.
- Track active tab in `useState` (default `'__all__'`).
- Render the tab bar using `@wordpress/components` `TabPanel` (already imported pattern elsewhere via `@wordpress/components`). If `TabPanel`'s children-render model is awkward for a flat filter, fall back to a plain `<ul class="acrossai-library-page__tabs">` with click handlers. Either is acceptable — prefer `TabPanel` for accessibility.
- When `'__all__'` is active, render every card unchanged.
- When a `tab_group` is active, map each card to a copy whose `slugs` array is filtered to entries where `slug.tabGroup === activeTab`; drop cards whose filtered list is empty.
- When the unique-group set is empty, do **not** render the tab bar — the page renders identically to today.

Reuse `LibraryCard` as-is. No changes inside the card component; it receives the trimmed `slugs` array and renders normally.

### Point 5 — Tab-bar styling

**File**: `src/scss/ability-library/admin.scss`

Add a small block after the `.acrossai-library-page__empty` rule (around line 35). Target the chosen tab element (either `.components-tab-panel__tabs` if `TabPanel` is used, or `.acrossai-library-page__tabs` for the fallback). Keep the styling native to wp-admin — minimal margin/padding, no custom branding.

### Point 6 — Tests

- **PHP**: Extend the existing Registry unit test that covers `sub_group` pass-through to also assert `tab_group` is carried through when present, omitted when missing, and sanitized when bogus chars are supplied.
- **JS**: Extend the existing `groupDefinitions()` Jest test file (the one updated in Feature 036 for `description`) to assert each slug now carries a `tabGroup` field.
- **JS** (new): If the active-tab filter is extracted into a named helper (e.g. `filterItemsByTabGroup`), add a Jest test for it covering the three cases — `__all__` returns input unchanged; matching tab returns trimmed slugs; non-matching tab drops empty cards.

---

## Files to Edit

```text
includes/Modules/Library/Ability_Definition.php
includes/Modules/Library/AcrossAI_Ability_Library_Registry.php
src/js/ability-library/components/LibraryPage.js
src/scss/ability-library/admin.scss
<existing Jest test file for groupDefinitions()>
<existing PHP test file for Registry sub_group pass-through>
```

No changes to:

- `includes/Modules/Library/AcrossAI_Ability_Library_Config.php` (saved config schema is unchanged).
- `includes/Modules/Library/Rest/*` (no REST surface change).
- `src/js/ability-library/components/LibraryCard.js` (the card receives a pre-filtered slug list).
- `admin/Partials/LibraryMenu.php` (page registration is unchanged; React still mounts to `#acrossai-library-root`).

---

## What Must NOT Change

- The on-disk saved-config shape (`{ enabled, mode, sub_keys }` per category).
- The REST API endpoints or response shapes under `/acrossai-abilities-library/v1/abilities/config`.
- The boot order: Registry collects at `init` priority 99, add-ons hook at priority 10 — unchanged.
- The `LibraryCard.js` rendering logic.
- The single-page-app mount-point structure (`#acrossai-library-root`).
- The existing per-card "All" vs "Specific" mode radio. (Page-level tabs and per-card mode are independent controls.)

---

## Validation Checklist

### Static + unit gates

- [ ] `composer phpstan` passes — array-key types for `tab_group` are correct.
- [ ] `composer phpcs` does not introduce new findings on the changed PHP files.
- [ ] `npm run lint:js` passes on the React edits.
- [ ] `composer test` — extended Registry test covers `tab_group` carry-through and sanitization.
- [ ] `npm test` — extended `groupDefinitions` test covers `tabGroup` threading, and the new tab-filter helper (if extracted) is tested.

### Build

- [ ] `npm run build` succeeds under Node ≥20 (per `DEC-NODE-20-BUILD-REQUIRED`).

### Manual smoke (Local site `wordpress-7-0`)

- [ ] Drop a throwaway mu-plugin with one `Ability_Definition` subclass declaring two abilities tagged `args['tab_group'] => 'sales'` and one tagged `args['tab_group'] => 'support'`; keep one untagged ability from any existing add-on.
- [ ] Visit `wp-admin/admin.php?page=acrossai-abilities-library`. Tab bar shows **All**, **Sales**, **Support**.
- [ ] **All** tab — every ability visible.
- [ ] **Sales** tab — only the two sales abilities visible; cards with no matching abilities are hidden; untagged ability is hidden.
- [ ] **Support** tab — only the support ability visible.
- [ ] Toggle a card under a non-`All` tab — existing save/load via REST still works (no regression in `LibraryCard` behaviour).

### No-add-on regression

- [ ] With the mu-plugin deactivated and no `tab_group` declared anywhere, the page renders identically to today's Feature 036 layout — tab bar is hidden.

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks


# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpstan
composer run phpcs

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```
