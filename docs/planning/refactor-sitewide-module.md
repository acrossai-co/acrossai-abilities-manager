# Planning: Refactor Sitewide Module into Abilities Module

This document outlines the full Spec-Kit workflow for decommissioning the 'Sitewide' module and consolidating all its logic (Database, Processors, Access Control) into the 'Abilities' module.

## Phase 1: Setup & Specification

Run these commands to initiate the refactor and define requirements:

```markdown
# 1. Create a numbered feature branch
/speckit.git.feature "refactor-sitewide-to-abilities"

# 2. Specify the refactor requirements
# Use the detailed prompt below for the description
/speckit.specify "Decommission 'Sitewide' module, consolidate all logic into 'Abilities' module, and rename all internal components to match."
```

### Detailed Description for `/speckit.specify`:

> **GOAL:** Unified Ability Management Architecture. Decommission the `Sitewide` module and merge all durable logic into the `Abilities` module.
>
> **1. DATABASE LAYER CONSOLIDATION (`includes/Modules/Abilities/Database/`)**
> - **Move & Rename Files:**
>   - `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php` → `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php`
>   - `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php` → `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`
>   - `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php` → `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php`
> - **Internal Class Updates:**
>   - Update namespaces to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database`.
>   - Rename classes to `AcrossAI_Abilities_Table`, `AcrossAI_Abilities_Schema`, and `AcrossAI_Abilities_Row`.
>   - In `AcrossAI_Abilities_Table`, ensure `AcrossAI_Abilities_Schema::class` is used.
> - **Consolidate Query Logic (`AcrossAI_Abilities_Query.php`):**
>   - Change `$table_schema` to `AcrossAI_Abilities_Schema::class`.
>   - Change `$item_shape` to `AcrossAI_Abilities_Row::class`.
>   - **Port the following methods from `AcrossAI_Sitewide_Query`:**
>     - `get_override_by_slug( string $slug )`
>     - `save_override( string $slug, array $fields )`
>     - `delete_override_by_slug( string $slug )`
>     - `get_all_overrides()`
>   - Update these ported methods to use `AcrossAI_Abilities_Row` and internal `prepare_fields_for_write` logic where appropriate.
>
> **2. PROCESSOR & LOGIC MERGE (`includes/Modules/Abilities/`)**
> - **Move & Rename Files:**
>   - `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` → `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`
>   - `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` → `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php`
> - **Internal Updates:**
>   - Update namespaces to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities`.
>   - In `AcrossAI_Abilities_Override_Processor`, update the `AcrossAI_Sitewide_Query` reference to `AcrossAI_Abilities_Query`.
>   - In `AcrossAI_Abilities_Access_Control`, update the class name and any internal prefix references.
>   - In `AcrossAI_Abilities_Processor.php`, update the `use` statement for `AcrossAI_Sitewide_Row` to point to the new `AcrossAI_Abilities_Row` location.
>
> **3. REST API CLEANUP**
> - **DELETIONS:**
>   - Delete `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php`.
>   - Delete the entire `includes/Modules/Sitewide/Rest/` directory and all its sub-controllers.
> - **NOTE:** The `Abilities` module's existing REST controllers (Read, Write, Category, Exposure) already handle the unified table via `AcrossAI_Abilities_Query`. No porting of `Sitewide` REST logic is required as it was redundant.
>
> **4. PLUGIN BOOTSTRAP (`includes/Main.php`)**
> - **`define_admin_hooks()` updates:**
>   - Replace `\AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table::instance()` with `\AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table::instance()`.
>   - **REMOVE** the instantiation and `register_routes` wiring for `AcrossAI_Sitewide_Rest_Controller`.
>   - Replace `AcrossAI_Sitewide_Access_Control` logic with the new `AcrossAI_Abilities_Access_Control` singleton and hook wiring.
> - **`define_public_hooks()` updates:**
>   - Replace `AcrossAI_Ability_Override_Processor` (Sitewide) with the new `Abilities` version.
> - **Submenu Cleanup:** Ensure `AcrossAI_Abilities_Menu` (the custom abilities submenu) registration is removed as part of this unified module cleanup (already covered in UI plan, but verify here).
>
> **5. REFACTORING & REMOVAL**
> - **Global Search & Replace:** `AcrossAI_Sitewide_` → `AcrossAI_Abilities_`.
> - **Namespace Alignment:** Ensure all `Includes\Modules\Sitewide` namespace references across the entire plugin are updated to `Includes\Modules\Abilities`.
> - **Final Cleanup:** Delete the `includes/Modules/Sitewide/` directory.
>
> **CONSTRAINTS:**
> - Maintain the singleton pattern and Loader singleton pattern for all moved classes.
> - All instance calls MUST follow the "Named variable before Loader call" rule in `Main.php`.
> - Do not modify the `Logger` module.
> - Ensure `AcrossAI_Abilities_Query` remains the single source of truth for DB interactions with `acrossai_abilities`.

## Phase 2: Planning & Validation

Generate the technical plan and validate it against project architecture and security standards:

```markdown
# 3. Generate technical plan with memory context
/speckit.memory-md.plan-with-memory

# 4. Validate plan against Constitution
/speckit.architecture-guard.governed-plan

# 5. Review plan for security gaps
/speckit.security-review.plan
```

## Phase 3: Task Generation

Break the plan into executable tasks and verify sequencing:

```markdown
# 6. Generate implementation tasks
/speckit.tasks

# 7. Validate tasks for architecture drift
/speckit.architecture-guard.governed-tasks

# 8. Review task sequencing for security
/speckit.security-review.tasks
```

## Phase 4: Implementation & Verification

Execute the code changes and perform local quality checks:

```markdown
# 9. Execute tasks with governance and security review
/speckit.architecture-guard.governed-implement

# 10. Verification (Manual Shell Commands)
# npm run build && composer run phpstan
```

## Phase 5: Review, Memory & Commit

Perform final audits and persist learnings before committing:

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
- [ ] No `includes/Modules/Sitewide/` directory exists.
- [ ] All database classes use the `Abilities` namespace and prefix.
- [ ] `includes/Main.php` successfully wires all `Abilities` module hooks.
- [ ] REST API `acrossai-abilities-manager/v1/sitewide/*` endpoints return 404.
- [ ] Custom Abilities and Overrides still function via the `Abilities` module.
- [ ] PHPStan level 8 passes with zero errors.
