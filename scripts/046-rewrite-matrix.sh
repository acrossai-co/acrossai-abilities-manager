#!/usr/bin/env bash
# 046-rewrite-matrix.sh — Feature 046 bulk PHP rewrite pipeline
#
# Applies the 9-step CHANGE-5 rewrites (spec 046) to every .php file under a
# target directory. Runs per-file with synchronous writes per
# BUG-PYTHON-STRREPLACE-PARTIAL-WRITE. Uses sed/perl only — no Python str_replace.
#
# Usage:
#   scripts/046-rewrite-matrix.sh [--dry-run] <target-directory> [<target-directory>...]
#
# Example:
#   scripts/046-rewrite-matrix.sh includes/Abilities admin/Partials/Core_Settings_Menu.php
#
# Order matters. Exact string transforms (5a–5d, 5h) run before partial-match
# transforms (5e–5f) so the manager-branded strings emitted by earlier passes
# are never re-rewritten. Rules 5g (identifier names) and 5i (docblock @package
# headers) are noted but not fully automated — see the WARN blocks below.

set -euo pipefail

DRY_RUN=0
if [[ "${1:-}" == "--dry-run" ]]; then
    DRY_RUN=1
    shift
fi

if [[ $# -lt 1 ]]; then
    echo "usage: $0 [--dry-run] <target-directory> [<target-directory>...]" >&2
    exit 2
fi

# Detect BSD vs GNU sed (macOS ships BSD sed).
if sed --version >/dev/null 2>&1; then
    SED_INPLACE=(sed -i)
else
    SED_INPLACE=(sed -i '')
fi

apply_file() {
    local file="$1"
    if [[ $DRY_RUN -eq 1 ]]; then
        echo "DRY: would rewrite $file"
        return
    fi

    # 5a — namespace declarations. Order: most-specific first.
    "${SED_INPLACE[@]}" \
        -e 's|namespace Acrossai_Core_Abilities\\Includes\\Admin\\Partials;|namespace AcrossAI_Abilities_Manager\\Admin\\Partials;|g' \
        -e 's|namespace Acrossai_Core_Abilities\\Includes\\Utilities\\|namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\|g' \
        -e 's|namespace Acrossai_Core_Abilities\\Includes\\Utilities;|namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities;|g' \
        -e 's|namespace Acrossai_Core_Abilities\\Includes\\Abilities\\|namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\|g' \
        "$file"

    # 5b — use imports and FQCN references (same substring pattern as 5a, without the leading `namespace `).
    "${SED_INPLACE[@]}" \
        -e 's|Acrossai_Core_Abilities\\Includes\\Admin\\Partials|AcrossAI_Abilities_Manager\\Admin\\Partials|g' \
        -e 's|Acrossai_Core_Abilities\\Includes\\Utilities|AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities|g' \
        -e 's|Acrossai_Core_Abilities\\Includes\\Abilities|AcrossAI_Abilities_Manager\\Includes\\Abilities|g' \
        -e 's|Acrossai_Core_Abilities\\Includes|AcrossAI_Abilities_Manager\\Includes|g' \
        -e 's|\\Acrossai_Core_Abilities\\|\\AcrossAI_Abilities_Manager\\|g' \
        "$file"

    # 5c — text domain string. Exact match; must run before 5e/5f partial-match rewrites.
    "${SED_INPLACE[@]}" \
        -e "s|'acrossai-core-abilities'|'acrossai-abilities-manager'|g" \
        -e 's|"acrossai-core-abilities"|"acrossai-abilities-manager"|g' \
        "$file"

    # 5d — plugin constants.
    "${SED_INPLACE[@]}" \
        -e 's|ACROSSAI_CORE_ABILITIES_PLUGIN_URL|ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL|g' \
        -e 's|ACROSSAI_CORE_ABILITIES_PLUGIN_PATH|ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH|g' \
        -e 's|ACROSSAI_CORE_ABILITIES_PLUGIN_BASENAME|ACROSSAI_ABILITIES_MANAGER_PLUGIN_BASENAME|g' \
        -e 's|ACROSSAI_CORE_ABILITIES_PLUGIN_FILE|ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE|g' \
        -e 's|ACROSSAI_CORE_ABILITIES_PLUGIN_NAME_SLUG|ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME_SLUG|g' \
        -e 's|ACROSSAI_CORE_ABILITIES_PLUGIN_NAME|ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME|g' \
        -e 's|ACROSSAI_CORE_ABILITIES_VERSION|ACROSSAI_ABILITIES_MANAGER_VERSION|g' \
        "$file"

    # 5e — visible label text. Case-sensitive so we don't clobber unrelated words.
    "${SED_INPLACE[@]}" \
        -e 's|Acrossai Core Abilities|Acrossai Abilities Manager|g' \
        -e 's|AcrossAI Core Abilities|AcrossAI Abilities Manager|g' \
        "$file"

    # 5f — category slug prefix (dash form) AND ability slug prefix (slash form).
    # Trailing dash matches category slugs (acrossai-core-abilities-plugins);
    # trailing slash matches ability slugs (acrossai-core-abilities/user-create).
    # The bare text-domain 'acrossai-core-abilities' was already handled by 5c.
    "${SED_INPLACE[@]}" \
        -e 's|acrossai-core-abilities-|acrossai-abilities-manager-|g' \
        -e 's|acrossai-core-abilities/|acrossai-abilities-manager/|g' \
        "$file"

    # 5h — singleton property PSR2 rename (DEC-SINGLETON-PSR2-PROPERTY).
    "${SED_INPLACE[@]}" \
        -e 's|\$_instance|$instance|g' \
        -e 's|self::\$_instance|self::\$instance|g' \
        "$file"

    # 5g — identifier names (class/function/method/constant names carrying
    # "Core Abilities" wording). Handled per-file — see WARN emitted below.
    if grep -qE 'Core[_ ]Abilities' "$file"; then
        echo "WARN 5g: $file still contains 'Core_Abilities' or 'Core Abilities' identifier text — review by hand"
    fi

    # 5i — docblock @package / @subpackage / @since. Handled per-file — see WARN.
    if grep -qE '@package[[:space:]]+Acrossai_Core_Abilities' "$file"; then
        echo "WARN 5i: $file @package docblock still references Acrossai_Core_Abilities — rewrite by hand"
    fi
}

for target in "$@"; do
    if [[ -d "$target" ]]; then
        while IFS= read -r -d '' file; do
            apply_file "$file"
        done < <(find "$target" -type f -name '*.php' -print0)
    elif [[ -f "$target" ]]; then
        apply_file "$target"
    else
        echo "skip: $target (not a file or directory)" >&2
    fi
done

echo "done"
