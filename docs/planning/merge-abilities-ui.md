# Planning: Merge Abilities UI & Consolidate Backend Modules

This document outlines the full Spec-Kit workflow for two primary objectives:
1. Merging the Custom Abilities UI into the main Manager page and removing the obsolete sitewide React application.
2. Decommissioning the 'Sitewide' backend module and consolidating all logic into the 'Abilities' module.

## PART 1: UI Merger & Sitewide App Decommissioning

### Phase 1: Setup & Specification (UI)

Run these commands to initiate the feature and define requirements:

```markdown
# 1. Create a numbered feature branch
/speckit.git.feature "merge-abilities-ui"

# 2. Specify the feature requirements
/speckit.specify "Collapse abilities UI into main manager page, remove sitewide application sources, and update webpack configuration."
```

#### Description for `/speckit.specify` (UI):
> **CONTEXT:** We are collapsing two admin pages into one and removing an obsolete 'sitewide' application.
> - **Target Page:** ?page=acrossai-abilities-manager (Main Manager Page)
> - **Source UI:** React app in `src/js/abilities/` (mounts to `#acrossai-abilities-root`)
> - **Obsolete UI:** React app in `src/js/sitewide/` (to be deleted)
>
> **CHANGES REQUIRED:**
> 1. Delete `src/js/sitewide/` and `src/scss/sitewide/`.
> 2. Remove 'js/sitewide' and 'css/sitewide' from `webpack.config.js`.
> 3. Update `admin/Main.php` to enqueue `abilities` assets on the manager page and remove `sitewide` logic.
> 4. Update `admin/Partials/Menu.php` to mount to `#acrossai-abilities-root`.
> 5. Disable submenu registration in `admin/Partials/AcrossAI_Abilities_Menu.php`.
> 6. Remove submenu hook wiring in `includes/Main.php`.

---

## PART 2: Backend Module Consolidation (Sitewide → Abilities)

### Phase 1: Setup & Specification (Backend)

Run these commands to initiate the refactor and define requirements:

```markdown
# 1. Create a numbered feature branch
/speckit.git.feature "refactor-sitewide-to-abilities"

# 2. Specify the refactor requirements
/speckit.specify "Decommission 'Sitewide' module, consolidate all logic into 'Abilities' module, and rename all internal components to match."
```

#### Description for `/speckit.specify` (Backend):
> **CONTEXT:** We are unifying the plugin's core logic into a single 'Abilities' module. The 'Sitewide' module is being completely removed, and its database, processor, and access-control logic is being merged into the 'Abilities' module.
>
> **CHANGES REQUIRED:**
>
> 1. **DATABASE CONSOLIDATION** (Move & Rename in `includes/Modules/Abilities/Database/`):
>    - Move `Sitewide_Table.php`, `Sitewide_Schema.php`, `Sitewide_Row.php` -> `Abilities_Table.php`, `Abilities_Schema.php`, `Abilities_Row.php`.
>    - Merge `AcrossAI_Sitewide_Query.php` into `AcrossAI_Abilities_Query.php`.
>    - Port methods: `save_override`, `delete_override_by_slug`, `get_all_overrides`, and `get_override_by_slug`.
>    - Update `Abilities_Query` to use `Abilities_Schema` and `Abilities_Row`.
>
> 2. **PROCESSOR & ACCESS CONTROL** (Move & Rename in `includes/Modules/Abilities/`):
>    - Move `Sitewide\AcrossAI_Ability_Override_Processor.php` -> `Abilities\AcrossAI_Abilities_Override_Processor.php`.
>    - Move `Sitewide\AcrossAI_Sitewide_Access_Control.php` -> `Abilities\AcrossAI_Abilities_Access_Control.php`.
>    - Update namespaces to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities`.
>
> 3. **REMOVE REDUNDANT REST CODE**:
>    - Delete `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php`.
>    - Delete the `includes/Modules/Sitewide/Rest/` directory.
>
> 4. **BOOTSTRAP UPDATES** (`includes/Main.php`):
>    - Update all singleton instantiations and hook wiring to point to the new `Abilities` module locations.
>    - Remove `Sitewide` REST orchestrator and `AcrossAI_Abilities_Menu` (submenu) registrations.
>
> 5. **GLOBAL REFACTING**:
>    - Search and replace all `AcrossAI_Sitewide_` → `AcrossAI_Abilities_`.
>    - Update `Sitewide` namespace references to `Abilities`.
>    - Delete the `includes/Modules/Sitewide/` folder.

---

## UNIVERSAL WORKFLOW (Phases 2 - 5)

Generate the technical plan, break it into tasks, and execute:

### Phase 2: Planning & Validation

```markdown
# 3. Generate technical plan with memory context
/speckit.memory-md.plan-with-memory

# 4. Validate plan against Constitution
/speckit.architecture-guard.governed-plan

# 5. Review plan for security gaps
/speckit.security-review.plan
```

### Phase 3: Task Generation

```markdown
# 6. Generate implementation tasks
/speckit.tasks

# 7. Validate tasks for architecture drift
/speckit.architecture-guard.governed-tasks

# 8. Review task sequencing for security
/speckit.security-review.tasks
```

### Phase 4: Implementation & Verification

```markdown
# 9. Execute tasks with governance and security review
/speckit.architecture-guard.governed-implement

# 10. Local Build & Lint (Manual Shell Commands)
# npm run build && npm run lint
```

### Phase 5: Review, Memory & Commit

```markdown
# 11. Consistency check (Spec ↔ Plan ↔ Code)
/speckit.analyze

# 12. Post-implementation architecture review
/speckit.architecture-guard.architecture-review

# 13. Final security audit of changed code
/speckit.security-review.staged

# 14. Extract durable knowledge to project memory
/speckit.memory-md.capture-from-diff

# 15. Commit with structured message
/speckit.git.commit
```

### Manual Verification Checklist
- [ ] `abilities.js` loads on `?page=acrossai-abilities-manager`.
- [ ] `sitewide.js` is NOT loaded on any page.
- [ ] React mounts to `#acrossai-abilities-root`.
- [ ] `window.acrossaiAbilitiesManager` is present.
- [ ] "Custom Abilities" menu item is removed.
- [ ] Logger page remains functional.
