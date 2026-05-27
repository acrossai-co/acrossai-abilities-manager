# Planning: Allowed Servers Checkbox List (Feature 016)

Replace the free-text `mcp_servers` input in AbilityForm with a visual checkbox
list of registered MCP servers fetched from the `wpb-mcp-servers-list` REST
endpoint.

---

## Implementation Prompt

> The `wpboilerplate/wpb-mcp-servers-list` package is already installed and
> `McpServersList::collect()` is already wired to `rest_api_init` at priority 20
> in `includes/Main.php`. The package also ships a `RestEndpoint` class that can
> expose those servers at `GET /wp-json/wpb-mcp-servers-list/v1/servers`, but it
> is never registered.
>
> In `AbilityForm.jsx` the "Allowed Servers" field (inside the MCP Exposure
> section, around line 984) is currently a plain `<input type="text">` where the
> user types `*` or comma-separated server IDs by hand. Replace it with a
> checkbox list that loads available servers from the REST endpoint.
>
> **Two changes required:**
>
> **1. PHP — `includes/Main.php`**
> Register the REST endpoint right after the existing `McpServersList::collect()`
> loader call in `define_admin_hooks()`. Store the class string in a named
> variable first (Boot Flow Rule AC-HOOKS-MAIN), then wire it:
>
> ```php
> $mcp_servers_rest = \WPBoilerplate\McpServersList\RestEndpoint::class;
> $this->loader->add_action( 'rest_api_init', $mcp_servers_rest, 'register', 20 );
> ```
>
> **2. JS — `src/js/abilities/components/AbilityForm.jsx`**
> Replace the `<input type="text">` block with a checkbox list:
> - On mount, call `apiFetch({ path: '/wpb-mcp-servers-list/v1/servers' })` and
>   store `data.servers` and `data.adapter_available` in local component state
>   (`useState`). No Redux changes needed.
> - `mcp_servers: null` means "all servers" — map this to an "All servers
>   (default)" checkbox that is checked when the value is null.
> - Render one checkbox per server showing `server.name` with `server.id` as
>   sub-text. Toggling a server adds/removes its ID from the array; un-checking
>   the last one patches `mcp_servers: null`.
> - Handle three edge states before showing the list: loading (spinner/text),
>   `adapter_available: false` (short notice, no list), empty servers array
>   (short notice, no list).
> - If `draftAbility.mcp_servers` contains an ID that is not in the fetched list,
>   still render it as a checked item so the saved value is not silently dropped.
> - Keep the existing `isNonDb` "Plugin declares:" hint below the list, reading
>   from `savedAbility._registry?.mcp_servers`.
>
> **Constraints:**
> - Do not change the `mcp_servers` data contract (`null` = all, array = list).
> - `apiFetch`, `useState`, `useEffect` are already imported — no new deps.
> - PHPStan level 8, PHPCS, ESLint, and webpack build must all pass clean.

---

## Tips

- **Priority order matters.** `collect()` and `RestEndpoint::register()` are both
  at P20 on `rest_api_init`. WordPress fires hooks in registration order within
  the same priority, so `collect()` (registered first) runs before `register()`
  — servers are available when the endpoint is built.

- **Static method as callable.** WordPress `add_action` accepts
  `[ ClassName::class, 'method' ]` or a plain string `'ClassName::method'` for
  static methods. Using the named variable `$mcp_servers_rest = RestEndpoint::class`
  satisfies Boot Flow Rule without needing a real instance.

- **`null` vs `[]` distinction is load-bearing.** `mcp_servers: null` = inherit /
  all servers. `mcp_servers: []` would mean "no servers" — never patch an empty
  array; always collapse it back to `null`.

- **`apiFetch` is nonce-aware.** No manual auth header needed; the WordPress
  REST nonce injected by `wp_localize_script` / `wp_add_inline_script` handles
  the `manage_options` capability check automatically in the admin context.

- **Test the unknown-ID fallback.** Save an ability with `mcp_servers: ["old-id"]`,
  then load the form — `"old-id"` should still appear checked even if that server
  is gone. This prevents silent data loss on edit.

---

## Speckit Workflow

```markdown
# 1. Branch
/speckit.git.feature "allowed-servers-checkbox-list"

# 2. Specify
/speckit.specify "Register wpb-mcp-servers-list RestEndpoint in Main.php and
replace the free-text mcp_servers input in AbilityForm with a checkbox list
populated via apiFetch."

# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
npm run build && composer run phpcs && composer run phpstan && npm run lint:js

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

- [ ] `GET /wp-json/wpb-mcp-servers-list/v1/servers` returns `200` with
      `adapter_available` and `servers[]`.
- [ ] "Allowed Servers" row shows a checkbox list, not a text input.
- [ ] "All servers (default)" is checked when `mcp_servers` is `null`.
- [ ] Checking a server checkbox saves its ID to `mcp_servers` array.
- [ ] Un-checking the last server collapses back to `mcp_servers: null`.
- [ ] Checking "All servers (default)" clears the array → `mcp_servers: null`.
- [ ] Adapter not active → notice shown, no list.
- [ ] Adapter active, no servers → "No MCP servers registered yet." notice.
- [ ] Unknown saved server ID still renders as a checked item.
- [ ] `isNonDb` "Plugin declares:" hint renders from `_registry.mcp_servers`.
- [ ] PHPStan, PHPCS, ESLint, and webpack build all pass clean.
