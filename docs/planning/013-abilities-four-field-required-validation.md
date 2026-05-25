# Planning Prompt: Abilities Four-Field Required Validation

## Feature: 013 — Enforce ability_slug, label, description, and category as Required Fields

---

## /speckit.specify prompt

```
/speckit.specify Enforce all four required fields (ability_slug, label, description, category) across the Abilities create/edit form and the PHP backend.

CONTEXT:
The abilities admin form (Add New + Edit) has four fields that must be filled before an ability
can be meaningfully registered or consumed by AI agents: ability_slug, label, description,
and category. The current state is inconsistent:

  | Field        | React required marker | Save blocked client-side? | PHP is_row_registrable | PHP Validator (non-empty)  |
  |-------------|----------------------|---------------------------|------------------------|----------------------------|
  | ability_slug | ✅  (slug format)    | Format-only (not empty)   | ✅                     | ✅ validate_slug_suffix    |
  | label        | ✅ *                 | ❌ no block                | ✅                     | ❌ null allowed            |
  | description  | ❌ marked "optional" | ❌ no block                | ❌ not checked         | ❌ no validator            |
  | category     | ✅ *                 | ❌ no block                | ✅                     | ❌ null allowed            |

Problems this creates:
1. A user can click "Add Ability" / "Save Changes" with empty label/description/category and
   receive a server error instead of an immediate inline message.
2. description is silently treated as optional even though it is shown to AI agents during
   capability discovery — an empty description produces a useless registration.
3. PHP is_row_registrable() skips description, so rows without descriptions are registered
   when they should be held as draft.
4. PHP validators for label and category accept null but also accept empty string '',
   producing registrations with blank labels/categories.

---

FILE MAP:
  src/js/abilities/components/AbilityForm.jsx
    — React form for Add New (mode=create) and Edit (mode=edit).
    — SLUG_PREFIX = 'acrossai-abilities/' (line ~250)
    — handleSlugChange() (line ~361): existing slug regex validation.
    — handleSave() (line ~417): calls dispatch.createAbility or dispatch.updateAbility.
    — formErrors state does NOT exist yet.
    — description field (lines ~734-756): label reads "optional" via className="lopt".

  includes/Modules/Abilities/AcrossAI_Abilities_Processor.php
    — is_row_registrable() (line ~120): checks ability_slug, label, category.
    — description check is absent.

  includes/Utilities/AcrossAI_Abilities_Validator.php
    — validate_label() (line ~205): accepts null, accepts ''.
    — validate_category() (line ~225): accepts null, accepts ''.
    — No validate_description() method exists.

  includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php
    — create handler (line ~140): calls AcrossAI_Abilities_Validator::validate_ability($fields, true).
    — update handler (line ~211): calls validate_ability on submitted fields only.

  src/scss/abilities/admin.scss
    — .field-error rule may or may not exist; ensure it has:
        color: #d63638; font-size: 11px; margin-top: 4px;

---

CHANGE 1 — src/js/abilities/components/AbilityForm.jsx (client-side required-field validation):

Scope: only Variant A (mode=create or mode=edit, where source=db and fields are editable).
Variant B (mode=override, inherited abilities) must NOT be affected.

1.1  Add formErrors state near the top of the component:
       const [formErrors, setFormErrors] = useState({
         slug_suffix: '',
         label: '',
         description: '',
         category: '',
       });

1.2  Add a pure validateRequiredFields(draft, slugSuffix) helper (outside the component or
     as a useCallback) that returns an errors object:
       {
         slug_suffix: !slugSuffix?.trim() ? __('This field is required.', 'acrossai-abilities-manager') : '',
         label: !draft.label?.trim()       ? __('This field is required.', 'acrossai-abilities-manager') : '',
         description: !draft.description?.trim() ? __('This field is required.', 'acrossai-abilities-manager') : '',
         category: !draft.category         ? __('This field is required.', 'acrossai-abilities-manager') : '',
       }

1.3  In handleSave(), before calling any dispatch method, when mode is 'create' or 'edit':
     - Call validateRequiredFields(draftAbility, slugSuffix).
     - If any value in the returned object is non-empty, call setFormErrors(errors) and return early.
     - Do not call setFormErrors on success path (leave errors in place until fields are filled).

1.4  Add onBlur handlers for each required field so errors appear as soon as the user leaves
     an empty field without waiting for a save attempt:
     - Slug suffix input: onBlur={() => { if (!slugSuffix.trim()) setFormErrors(prev => ({...prev, slug_suffix: __('This field is required.', 'acrossai-abilities-manager')})); else setFormErrors(prev => ({...prev, slug_suffix: ''})); }}
     - Label input: similar pattern checking draftAbility.label
     - Description textarea: similar pattern checking draftAbility.description
     - Category select: similar pattern checking draftAbility.category
     Also clear the error for each field on its onChange (when the field gains a value).

1.5  Render each field-level error immediately below the input/select/textarea (no toasts,
     no top-level banners):
       {formErrors.slug_suffix && <div className="field-error">{formErrors.slug_suffix}</div>}
       {formErrors.label       && <div className="field-error">{formErrors.label}</div>}
       {formErrors.description && <div className="field-error">{formErrors.description}</div>}
       {formErrors.category    && <div className="field-error">{formErrors.category}</div>}

1.6  Compute hasRequiredErrors:
       const hasRequiredErrors = (mode === 'create' || mode === 'edit') &&
         (!slugSuffix?.trim() || !draftAbility.label?.trim() ||
          !draftAbility.description?.trim() || !draftAbility.category);

1.7  Apply disabled state to the primary save button ("Add Ability" / "Save Changes") and
     the sticky bar save button when hasRequiredErrors is true:
       style={{ opacity: hasRequiredErrors ? 0.5 : 1, pointerEvents: hasRequiredErrors ? 'none' : 'auto' }}
       aria-disabled={hasRequiredErrors}
     Do NOT apply this disabled state to the "Save as Draft" button — draft saving is
     intentionally allowed for incomplete rows.

1.8  Change the description field label: remove the <span className="lopt"> "optional" span
     and replace it with <span className="req"> *</span> to match label, slug, category.

1.9  Clear all formErrors when the component resets to a new ability (e.g., after successful
     create navigates to edit mode) by adding setFormErrors({slug_suffix:'',label:'',description:'',category:''})
     in the savedAbility useEffect that resets slugSuffix.

---

CHANGE 2 — includes/Modules/Abilities/AcrossAI_Abilities_Processor.php (is_row_registrable):

Add a description check immediately after the existing category check:

  private function is_row_registrable( AcrossAI_Abilities_Row $row ): bool {
      if ( '' === $row->ability_slug ) {
          return false;
      }
      if ( empty( $row->label ) ) {
          return false;
      }
      if ( empty( $row->category ) ) {
          return false;
      }
      if ( empty( $row->description ) ) {   // ← ADD THIS
          return false;
      }
      return true;
  }

Update the method docblock to list all four checked fields.

---

CHANGE 3 — includes/Utilities/AcrossAI_Abilities_Validator.php (tighten label + category + add description):

3.1  validate_label(): reject empty string as well as null-when-required.
     The current method allows '' to pass. Add after the is_string() check:
       if ( '' === trim( $label ) ) {
           return new \WP_Error( 'invalid_label', __( 'Ability label must not be empty.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
       }
     Keep null-allowed behaviour (override rows may omit label).

3.2  validate_category(): same treatment — reject empty string:
       if ( '' === trim( $category ) ) {
           return new \WP_Error( 'invalid_category', __( 'Ability category must not be empty.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
       }

3.3  Add validate_description() as a new public static method:
       /**
        * Validate a description string.
        *
        * @since  0.1.0
        * @param  mixed $description Value to validate.
        * @return true|\WP_Error
        */
       public static function validate_description( $description ) {
           if ( null === $description ) {
               return true; // nullable for update partial patches
           }
           if ( ! is_string( $description ) ) {
               return new \WP_Error( 'invalid_description', __( 'Ability description must be a string.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
           }
           if ( '' === trim( $description ) ) {
               return new \WP_Error( 'invalid_description', __( 'Ability description must not be empty.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
           }
           if ( mb_strlen( $description ) > 1000 ) {
               return new \WP_Error( 'invalid_description', __( 'Ability description must not exceed 1000 characters.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
           }
           return true;
       }

3.4  Add a DESCRIPTION_MAX_LENGTH constant: const DESCRIPTION_MAX_LENGTH = 1000;

3.5  Wire validate_description() into the main validate_ability() orchestrator method so it
     runs whenever 'description' key is present in the validated fields array.

---

CHANGE 4 — includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php (create guard):

In the create handler, after sanitization and before calling the DB insert, assert that
description was supplied and is non-empty. The validate_ability() call already validates
format if description is present; add a presence check:

  if ( empty( $fields['description'] ) ) {
      return new \WP_Error(
          'missing_description',
          __( 'Ability description is required.', 'acrossai-abilities-manager' ),
          array( 'status' => 400 )
      );
  }

Apply the same presence checks for label and category in the create handler (they may
already be present — verify against the existing code and add if absent):

  if ( empty( $fields['label'] ) ) {
      return new \WP_Error( 'missing_label', __( 'Ability label is required.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
  }
  if ( empty( $fields['category'] ) ) {
      return new \WP_Error( 'missing_category', __( 'Ability category is required.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
  }

Do NOT add presence checks to the update handler — partial updates (PATCH-style) are valid.

---

CHANGE 5 — src/scss/abilities/admin.scss (ensure .field-error rule):

Confirm or add:
  .field-error {
    color: #d63638;
    font-size: 11px;
    margin-top: 4px;
  }

If the rule already exists from feature 011 with those exact values, no change needed.

---

CONSTRAINTS:
- Do not touch any other PHP files (DB layer, sanitizer, migration, etc.).
- Do not modify webpack.config.js.
- Variant B (mode=override) form must not show any of these validation errors — the save
  path for override writes only site_allowed/show_in_rest/show_in_mcp/mcp_type.
- The "Save as Draft" button must remain fully clickable even when required fields are empty;
  the server accepts partial drafts.
- formErrors state must be reset whenever savedAbility changes (new ability loaded / created).
- The disabled visual state on the save button is purely CSS (opacity + pointer-events) —
  do NOT use the HTML disabled attribute, which removes the button from tab order.
- All new user-visible strings must be wrapped in __( '...', 'acrossai-abilities-manager' ).

---

VERIFICATION:
1. Add New form, all four fields empty → clicking "Add Ability" shows four inline
   "This field is required." messages and makes NO network request to POST /abilities.
2. Add New form, fill slug only → three errors remain, slug error clears.
3. Add New form, fill all four → save button enabled (full opacity), POST /abilities succeeds.
4. Blur off empty label field → inline error appears without clicking save.
5. Blur off empty description field → inline error appears.
6. Type into the field after blur error → error clears immediately on change.
7. "Save as Draft" with all fields empty → API request IS made (no client block).
8. Edit form (mode=edit): same four-field validation applies; slug field is readOnly but
   already has a value so it never triggers a slug_suffix error.
9. Variant B (Override Inherited): save button enabled, no required-field errors shown.
10. PHP unit / integration: row with empty description is rejected by is_row_registrable().
11. REST POST /abilities without description body → HTTP 400 with code 'missing_description'.
12. REST POST /abilities without label → HTTP 400 with code 'missing_label'.
13. REST POST /abilities without category → HTTP 400 with code 'missing_category'.
14. npm run build succeeds (nvm use 20 && npm run build).
15. No PHP fatal errors or notices after the change (check WP debug.log).
```

---

## Current State Summary

| Field        | React required marker | Save blocked? | PHP is_row_registrable | PHP Validator empty-string |
|-------------|----------------------|---------------|------------------------|----------------------------|
| ability_slug | ✅ *                 | Format only   | ✅                     | ✅                         |
| label        | ✅ *                 | ❌            | ✅                     | ❌ ('' passes)             |
| description  | ❌ "optional"        | ❌            | ❌                     | ❌ no validator            |
| category     | ✅ *                 | ❌            | ✅                     | ❌ ('' passes)             |

After this spec: all four fields are required end-to-end — client blocks save, PHP skips
registration, and REST rejects create requests that omit any of the four fields.

## Notes

- The is_row_registrable() PHP guard was previously intentionally left without description
  (011 spec, line 85: "the PHP may be updated in a future spec") — this is that future spec.
- validate_label and validate_category remain null-tolerant because update (partial-patch)
  flows do not always supply every field; the null-check is for override rows that inherit
  from plugin-registered abilities.
- description max length of 1000 chars is a reasonable limit for AI agent consumption;
  adjust if product requirements differ.
