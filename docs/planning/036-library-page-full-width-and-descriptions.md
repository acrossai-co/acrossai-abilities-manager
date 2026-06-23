# Planning: Library Page Full Width and Ability Descriptions (Feature 036)

Show each ability's `description` directly under its row on the Ability Library
page (`wp-admin/admin.php?page=acrossai-abilities-library`), and let the page
span the full width of the WordPress admin content area instead of being
constrained to 900px.

The data is already available — `Ability_Definition::push_definition()` already
stores `args` (including `description`) on every row, and the Registry already
allows `description` in `ALLOWED_ARGS_FIELDS`. The fix is to thread that field
through the React render path and update the SCSS width constraint. No
data-collection or REST contract changes.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "036-library-page-full-width-and-descriptions"

# 2. Specify
/speckit.specify "Update the Ability Library admin page (wp-admin/admin.php?page=acrossai-abilities-library) so that:
(1) Each ability row inside the Specific panel AND the read-only All panel displays the ability's description directly under its label, in a smaller muted style. The description value already exists on every Library row at definition.args.description (set by Ability_Definition::push_definition() and allowlisted by AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS); it is currently dropped by the front-end grouping helper before reaching the LibraryCard component. No REST contract, saved config, or data-collection logic changes — only the React render path consumes a field that is already present.
(2) The page expands to the full width of the WordPress admin content area instead of being constrained to 900px. Today src/scss/ability-library/admin.scss line 19 sets max-width: 900px on .acrossai-library-page. Remove that constraint so the page uses the available WP admin width. Individual card content should still read comfortably; no min/max card width is introduced.
(3) When an ability has no description (description is empty, missing, or only whitespace), the row renders exactly as it does today — the label/slug line only. No empty wrapper, no separator, no placeholder text.
(4) HTML rendered inside description must be safe. The description originates from a PHP-side definition that already passes through ALLOWED_ARGS_FIELDS filtering in the Registry, but is not HTML-sanitized as wp_kses_post output. Treat it as plain text in React (render as a string in a <span>/<div>, do not use dangerouslySetInnerHTML)."
```

---

## Background — Current Render Path

| Layer | File | Today |
|------|------|------|
| Definition source | `includes/Modules/Library/Ability_Definition.php` (lines 61–88) | `push_definition()` stores the entire `args` array on each row, including `description` |
| Registry validation | `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` (lines 61–72, 188–191) | `args` is filtered through `ALLOWED_ARGS_FIELDS`; `description` IS in the allowlist and survives |
| Data injection | `admin/Main.php` (lines 294–318) | `wp_add_inline_script()` JSON-encodes `Registry::get_definitions()` into `window.acrossaiAbilityLibraryData.definitions`; the `args` sub-array (including `description`) is already on the wire |
| React grouping | `src/js/ability-library/components/LibraryPage.js` (lines 13–45) | `groupDefinitions()` destructures only `category`, `category_label`, `slug`, `slug_label`, `name`, `sub_group`, `sub_group_label`. **Drops `args.description` here.** |
| Card render | `src/js/ability-library/components/LibraryCard.js` (lines 155–179) | Renders `slugLabel \|\| name` only. No description slot. |
| Width constraint | `src/scss/ability-library/admin.scss` (line 19) | `.acrossai-library-page { max-width: 900px; }` |

So the change is small and contained: thread one field, render it conditionally, drop one CSS rule.

---

## CHANGE-1 — Thread `description` Through `groupDefinitions()`

**File**: `src/js/ability-library/components/LibraryPage.js`

Pull `args.description` off each raw definition and copy it onto the slug entry
passed to `LibraryCard`. Read defensively because `args` is optional and may not
exist on legacy/partial test fixtures.

Current shape of the destructure block (lines 16–24):

```js
const {
    category,
    category_label: categoryLabel,
    slug,
    slug_label: slugLabel,
    name,
    sub_group: subGroup,
    sub_group_label: subGroupLabel,
} = def;
```

Target shape:

```js
const {
    category,
    category_label: categoryLabel,
    slug,
    slug_label: slugLabel,
    name,
    sub_group: subGroup,
    sub_group_label: subGroupLabel,
    args,
} = def;

const description =
    typeof args?.description === 'string' ? args.description.trim() : '';
```

Update the slug push (current lines 35–42) to include `description`:

```js
group.slugs.push({
    slug,
    slugLabel,
    name,
    subGroup: subGroup || '',
    subGroupLabel: subGroupLabel || '',
    description,
});
```

Rules:
- Trim whitespace once here so downstream truthiness checks (`description ? ...`) work without re-trimming.
- Do not introduce a fallback such as `description || slugLabel` — when absent, the row stays single-line by design.
- Do not pass the entire `args` object through to the card; only the `description` string is needed.

---

## CHANGE-2 — Render Description Under Each Ability Row

**File**: `src/js/ability-library/components/LibraryCard.js`

Both branches of the slug renderer (mode === 'specific' checkbox row and
mode === 'all' read-only row) need to display the description when present.

Update the destructure inside the `items.map` (current line 155):

```js
{items.map(({ slug, slugLabel, name, description }) =>
```

### Specific mode (interactive checkboxes)

Today (lines 156–170):

```jsx
mode === 'specific' ? (
    <CheckboxControl
        __nextHasNoMarginBottom
        key={slug}
        label={slugLabel || name}
        checked={slugsConfig[slug] ?? false}
        onChange={(value) =>
            update({
                sub_keys: {
                    ...slugsConfig,
                    [slug]: value,
                },
            })
        }
    />
) : ( /* ... */ )
```

Target — wrap the checkbox + description in a small container so the description
sits indented under the label:

```jsx
mode === 'specific' ? (
    <div key={slug} className="acrossai-library-card__slug-row">
        <CheckboxControl
            __nextHasNoMarginBottom
            label={slugLabel || name}
            checked={slugsConfig[slug] ?? false}
            onChange={(value) =>
                update({
                    sub_keys: {
                        ...slugsConfig,
                        [slug]: value,
                    },
                })
            }
        />
        {description && (
            <p className="acrossai-library-card__slug-description">
                {description}
            </p>
        )}
    </div>
) : ( /* ... */ )
```

Move `key={slug}` to the outer `<div>` to avoid duplicate-key warnings.

### All mode (read-only row)

Today (lines 171–178):

```jsx
<div
    key={slug}
    className="acrossai-library-card__slug-readonly"
>
    {slugLabel || name}
</div>
```

Target:

```jsx
<div
    key={slug}
    className="acrossai-library-card__slug-readonly"
>
    <span className="acrossai-library-card__slug-readonly-label">
        {slugLabel || name}
    </span>
    {description && (
        <span className="acrossai-library-card__slug-readonly-description">
            {description}
        </span>
    )}
</div>
```

The bullet `::before` already attaches to the row container; descriptions sit on
the same flow without a second bullet.

Rules:
- Render `description` as a plain string. Do NOT use `dangerouslySetInnerHTML`. Definition `description` values are not pre-sanitized as HTML by the Registry.
- Guard with `description &&` (truthy after trim). Do not render an empty paragraph or wrapper when the value is empty.
- Do not modify `groupBySubGroupPreservingOrder()` — the new field travels alongside the existing slug objects without affecting grouping.

---

## CHANGE-3 — Description Styles

**File**: `src/scss/ability-library/admin.scss`

Add styles for the new description elements inside the existing
`.acrossai-library-card` block.

### Specific mode

Add a slug-row container and an indented description paragraph:

```scss
// Wraps a single checkbox + its optional description in the Specific panel.
&__slug-row {
    display:        flex;
    flex-direction: column;
    gap:            2px;
}

&__slug-description {
    margin:        0 0 0 24px; // Align under the checkbox label, not the box.
    font-size:     12px;
    color:         $muted;
    line-height:   1.4;
}
```

The `24px` left offset matches `@wordpress/components` `CheckboxControl`'s
checkbox+gap width so the description aligns under the label, not the
checkbox. If a future WPDS upgrade changes the spacing, only this one number
needs to move.

### All mode

The current `&__slug-readonly` block (lines 122–134) renders a single text line
with a `::before` bullet. Extend it to lay out label + description as a column:

```scss
&__slug-readonly {
    font-size:   13px;
    color:       $muted;
    padding-left: 14px;
    position:    relative;
    display:     flex;
    flex-direction: column;
    gap:         2px;

    &::before {
        content:   '\2022'; // Bullet.
        position:  absolute;
        left:      0;
        top:       0;
        color:     $muted;
    }
}

&__slug-readonly-description {
    font-size:   12px;
    color:       $muted;
    line-height: 1.4;
}
```

The `&__slug-readonly-label` class does not need rules unless visual tuning
turns out to be necessary — declaring the class makes future targeting easy
without restyling now.

---

## CHANGE-4 — Remove the 900px Width Constraint

**File**: `src/scss/ability-library/admin.scss`

Current (line 19):

```scss
.acrossai-library-page {
    padding-top: 8px;
    position:    relative;
    max-width:   900px;
}
```

Target:

```scss
.acrossai-library-page {
    padding-top: 8px;
    position:    relative;
    // Use the full WordPress admin content width; no artificial cap.
}
```

Drop the `max-width` declaration entirely. Do not replace it with `100%` —
block elements default to that, and an explicit `100%` would be redundant.

### WordPress `.wrap` interaction

The PHP partial in `admin/Partials/LibraryMenu.php` (around line 100) wraps the
root in `<div class="wrap">`. Modern WP admin does not constrain `.wrap` to a
fixed width — it uses the available admin column. Removing the React-side cap
is sufficient to make the page span the available area.

Do NOT override `.wrap` width globally. Confine all width changes to
`.acrossai-library-page`.

---

## What Must NOT Change

- Do not modify `Ability_Definition::push_definition()`, the Registry, or
  `admin/Main.php` data injection. `description` is already on the wire.
- Do not modify the REST shape served by
  `AcrossAI_Ability_Library_Config_Controller` — saved config keys are still
  `enabled`, `mode`, `sub_keys`.
- Do not add `description` to saved configuration. It is purely a display field
  pulled fresh from each definition on every page load.
- Do not add `description` to `OPTIONAL_FIELDS` or change the Registry
  allowlist. The field already passes through inside `args` and is intentionally
  not promoted to a top-level row field.
- Do not render description via `dangerouslySetInnerHTML`.
- Do not change `groupBySubGroupPreservingOrder()` or its unit-tested signature.
- Do not change the page slug, admin menu position, or React mount node ID
  (`#acrossai-library-root`).
- Do not introduce a card-level `max-width` to replace the page-level one. Per
  the requirement, the page should be full width.

---

## Expected Files Changed

```text
src/js/ability-library/components/LibraryPage.js
src/js/ability-library/components/LibraryCard.js
src/scss/ability-library/admin.scss
```

No PHP changes. No REST changes. No new dependencies. No migration.

---

## Validation Checklist

### Data flow

- [ ] Open `wp-admin/admin.php?page=acrossai-abilities-library` in the browser.
- [ ] `console.log(window.acrossaiAbilityLibraryData.definitions[0].args.description)` shows the expected description string for at least one definition.
- [ ] React DevTools shows the `LibraryCard` `item.slugs[i].description` field populated for rows whose definitions provide one.

### UI — Specific mode

- [ ] Switch a card to "Specific". Each checkbox row that has a description shows it as smaller muted text directly under the label.
- [ ] The description sits indented under the checkbox label, not under the checkbox box itself.
- [ ] A checkbox row with no description renders as a single line, with no empty paragraph or extra spacing.

### UI — All mode

- [ ] Switch a card to "All". Each read-only row shows label + indented description under the bullet.
- [ ] No double bullets; bullet still appears at the row start.
- [ ] Rows without a description render as a single line.

### Width

- [ ] On a 1920px viewport, the page content (cards) extends past 900px and uses the available WordPress admin content area.
- [ ] On a narrow viewport (e.g. 1024px), cards still render without horizontal scroll.
- [ ] Mobile/responsive WordPress admin still wraps gracefully (`.wrap` handles this; no new media queries required).

### Regression

- [ ] Saving config via toggle/All/Specific/checkbox still POSTs the same shape to `acrossai-abilities-library/v1/abilities/config` (verify in DevTools Network panel: payload keys are still `enabled`, `mode`, `sub_keys`).
- [ ] Reloading the page restores the previously saved toggle/mode/checkbox state.
- [ ] Per-card expand/collapse chevron still toggles the slugs panel (Feature 033 contract intact).
- [ ] Sub-group headings still appear above the checkboxes that declare a `sub_group`.

### Quality gates

- [ ] `composer run phpstan` — passes (no PHP changes expected to affect this).
- [ ] `npm run build` — clean build, no React warnings about duplicate keys.
- [ ] Jest tests for `groupBySubGroupPreservingOrder` still pass; add a test that confirms `description` is passed through `groupDefinitions()` into the grouped slug entry.

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
npm run build

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```
