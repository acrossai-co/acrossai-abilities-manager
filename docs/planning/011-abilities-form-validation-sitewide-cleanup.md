# Planning Prompt: Abilities Form Validation + Sitewide Asset Cleanup

## Feature: 011 — Abilities Form Validation and Sitewide Asset Cleanup

---

## /speckit.specify prompt

```
/speckit.specify Abilities form required-field validation and dead sitewide asset cleanup.

CONTEXT:
The plugin has two separate concerns that must be addressed together as they both relate to the abilities admin UI:

1. SITEWIDE ASSET CLEANUP: admin/Main.php still declares $sitewide_asset_file, loads build/js/sitewide.asset.php in the constructor, and has wp_register_script/wp_enqueue_script/wp_add_inline_script calls for 'acrossai-abilities-sitewide' script and 'acrossai-abilities-sitewide' style. These build artifacts no longer exist (no src/js/sitewide directory, no sitewide entry in webpack.config.js, no build/js/sitewide.js). These dead references cause an include() on a non-existent file and pollute the enqueue lifecycle. They must be fully removed from admin/Main.php.

2. REACT FORM VALIDATION: The Custom Abilities form (AbilityForm.jsx) allows a user to attempt saving an ability without filling in all four required fields: ability_slug (the slug suffix input), label, description, and category. The PHP processor (AcrossAI_Abilities_Processor.php) already guards against registering incomplete rows via is_row_registrable() which checks ability_slug, label, and category. The REST write controller also validates these. However there is no client-side enforcement — the form lets users submit with empty fields and receives a 422 server error, which is a poor user experience. Both the Add New flow and the Edit flow must enforce these four fields before enabling save.

---

CHANGE 1 — admin/Main.php (remove dead sitewide references):
- Remove the $sitewide_asset_file property declaration.
- Remove the line: $this->sitewide_asset_file = include ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/sitewide.asset.php';
- Remove the on_abilities/on_logs/on_custom guard block that controls sitewide enqueue — this gating logic is now only needed for the abilities bundle (already gated by is_abilities_custom_page / is_manager_page) and logger bundle (already gated by is_logs_page).
- In enqueue_styles(): remove wp_register_style('acrossai-abilities-sitewide', ...) and wp_enqueue_style('acrossai-abilities-sitewide').
- In enqueue_scripts(): remove wp_register_script('acrossai-abilities-sitewide', ...), wp_enqueue_script('acrossai-abilities-sitewide'), and the wp_add_inline_script block that sets window.acrossaiAbilitiesSitewide.
- No other properties or methods should be touched.

CHANGE 2 — src/js/abilities/components/AbilityForm.jsx (client-side validation):
The four required fields are:
  - ability_slug: the slug suffix input (px-inp field inside the px-wrap prefix block) — must not be empty
  - label: the label text input — must not be empty
  - description: the description textarea — must not be empty
  - category: the category select — must not be empty / must have a real selection (not the placeholder)

Validation rules:
- Validate on attempt to save (clicking "Add Ability", "Save Changes", or "Save as Draft")
- Also validate on blur for each individual field (show the field-level error as soon as the user leaves that field empty)
- Validation errors appear as inline red text directly below the field (not a toast, not a top-level banner)
- While any required field is empty, the primary save button ("Add Ability" / "Save Changes") must be visually disabled (opacity 0.5, pointer-events none, aria-disabled=true) — but do NOT disable "Save as Draft" because draft saving allows partial data
- The sticky bar save button must follow the same disabled state
- Error message copy: "This field is required."
- Field-level error class: .field-error { color: #d63638; font-size: 11px; margin-top: 4px; }
- When all four required fields have values, the save button becomes enabled again
- Variant B (override/inherited) does NOT need this validation — only Variant A (source=db, editable form) applies

Implementation approach:
- Add a formErrors state object: { slug_suffix: '', label: '', description: '', category: '' }
- Add a validateRequiredFields(draft) helper that returns a populated errors object
- Call validateRequiredFields before any save action; if errors exist, set formErrors and return early (do not call API)
- On blur of each required field, run field-specific validation and update formErrors for that field only
- isDirty calculation is unaffected — validation state is separate from dirty state
- The "Save as Draft" button bypasses the required-fields check and always attempts to save (the server will accept partial drafts)

CONSTRAINTS:
- Do not modify any PHP business logic files (processors, validators, REST controllers, DB query classes)
- Do not modify webpack.config.js — no new entries needed
- Do not modify src/scss/ files except to add the .field-error rule to src/scss/abilities/admin.scss
- The is_row_registrable() PHP guard remains as a server-side safety net — client validation is additive, not a replacement
- description is added as a required field in the React form even though the current PHP is_row_registrable() does not check it — this is intentional to improve UX; the PHP may be updated in a future spec

VERIFICATION:
1. Admin page loads without PHP fatal errors (no include() failure for sitewide.asset.php)
2. Browser DevTools Network tab on ?page=acrossai-abilities-manager shows build/js/sitewide.js NOT requested
3. window.acrossaiAbilitiesSitewide is undefined in the browser console
4. Add New form: clicking "Add Ability" with all four fields empty shows four inline "This field is required." errors and does NOT call POST /abilities
5. Add New form: filling slug_suffix only, clicking save — three remaining field errors appear, slug error disappears
6. Add New form: filling all four fields — save button becomes enabled and POST /abilities succeeds
7. Blur off an empty label field — inline error appears immediately without clicking save
8. "Save as Draft" with empty fields — attempts API call (no client block), server handles validation
9. Edit form: same validation applies when editing an existing ability
10. Variant B (Override Inherited) form — no required-field errors shown, save works as before
11. Logs page (?page=acrossai-abilities-logs) still loads logger JS — no regression
12. npm run build not required — PHP change only for cleanup; SCSS + JSX change requires build (nvm use 20 && npm run build)
```

---

## Notes

- The sitewide source directories (`src/js/sitewide/`, `src/scss/sitewide/`) do not exist — no file deletions needed there.
- No webpack.config.js changes needed — no `js/sitewide` entry exists.
- The `build/js/sitewide.js` artifact also does not exist in the build output.
- The only sitewide cleanup work is removing dead references in `admin/Main.php`.
- PHP is_row_registrable() currently checks: ability_slug, label, category (not description). React adds description as a 4th required field for better UX.
