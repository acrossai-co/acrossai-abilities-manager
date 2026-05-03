# MCP Server Integration Guide

This document explains how to integrate MCP server discovery with the AcrossAI Abilities Manager plugin.

## Overview

The AcrossAI Abilities Manager allows site administrators to control which MCP servers have access to each ability. To enable this feature, your MCP adapter plugin must:

1. Implement the `acrossai_abilities_manager_get_mcp_servers` action hook to advertise available servers
2. Optionally use the `should_expose_to_mcp_server()` method at runtime to check visibility

## Registering Available Servers

When the admin edit form loads, the plugin fires the `acrossai_abilities_manager_get_mcp_servers` action to discover available MCP servers. Your plugin should hook into this action and populate the servers array.

### Example Implementation

```php
<?php
namespace MyPlugin\MCP;

add_action( 'acrossai_abilities_manager_get_mcp_servers', function( &$servers ) {
	// Discover your configured MCP servers
	$my_servers = MyMCPRegistry::get_all_servers();
	
	foreach ( $my_servers as $server_config ) {
		$servers[] = array(
			'id'    => $server_config['identifier'],
			'label' => $server_config['display_name'],
		);
	}
} );
```

### Server Array Format

Each server entry must be an associative array with:

```php
[
	'id'    => 'unique-server-identifier',  // string, required
	'label' => 'Human-Readable Server Name', // string, required
]
```

## Runtime Visibility Checks

At runtime, when an MCP server requests abilities, you can check whether an ability should be exposed using the `should_expose_to_mcp_server()` method from the `Override_Applier` class.

### Example Usage

```php
<?php
namespace MyPlugin\MCP;

use AcrossAI_Abilities_Manager\Runtime\Override_Applier;

function get_abilities_for_mcp_server( $server_id ) {
	$abilities = wp_get_abilities();
	$allowed   = [];
	
	foreach ( $abilities as $ability ) {
		if ( Override_Applier::should_expose_to_mcp_server( $ability->slug, $server_id ) ) {
			$allowed[] = $ability;
		}
	}
	
	return $allowed;
}
```

### Visibility Rules

The `should_expose_to_mcp_server()` method uses the stored override values to determine visibility:

- **Disabled for MCP**: `mcp_public = null` → Returns `false` (never exposed)
- **Allow in all servers**: `mcp_public = true` → Returns `true` (exposed to all)
- **Allow in specific servers**: `mcp_public = false, mcp_servers = [...]` → Returns `true` only if the server is in the list

If no override exists for an ability, it returns `false` by default (not exposed to MCP).

## Testing the Integration

To test your integration:

1. Install both your MCP adapter plugin and the AcrossAI Abilities Manager plugin
2. Activate both plugins
3. Go to **Tools → Ability Manager** in WordPress admin
4. Edit any ability and look for the **MCP Visibility** section
5. Select **Allow in specific MCP servers**
6. Verify that your servers appear in the list

If no servers appear, check:
- Your plugin is activated and the hook is registered
- The hook fires at priority 10 or lower (so it runs before the form renders)
- The servers array structure matches the required format

## Troubleshooting

### "No MCP servers available" message appears

**Cause:** The `acrossai_abilities_manager_get_mcp_servers` hook was not implemented, or no plugin hooked into it with server data.

**Solution:**
- Verify your MCP adapter plugin is activated
- Check that your plugin's hook implementation is correct (uses the action name exactly)
- Add debug logging to confirm the hook is being called:

```php
add_action( 'acrossai_abilities_manager_get_mcp_servers', function( &$servers ) {
	error_log( 'MCP server discovery hook called' );
	$servers[] = array( 'id' => 'test-server', 'label' => 'Test Server' );
} );
```

### Server IDs are being altered

**Cause:** Server IDs are sanitized with `sanitize_text_field()` to prevent issues. If your IDs contain special characters, they will be modified.

**Solution:** Use simple, alphanumeric server identifiers (e.g., `my-server-1` instead of `my/server:1`).

### Runtime filtering not working

**Cause:** The `should_expose_to_mcp_server()` method needs the namespace-qualified slug (`provider/ability`) to find the override.

**Solution:** Always pass the full ability slug:

```php
// ✅ Correct
Override_Applier::should_expose_to_mcp_server( 'ai/image-import', $server_id );

// ❌ Wrong
Override_Applier::should_expose_to_mcp_server( 'image-import', $server_id );
```

## Best Practices

1. **Register servers early:** Hook at priority 10 or lower so servers are available when needed
2. **Keep IDs stable:** Once a server is assigned to an ability, changing the ID will break the configuration
3. **Provide clear labels:** Use user-friendly names so admins know which server is which
4. **Test filtering:** Verify that abilities are correctly filtered at runtime using the `should_expose_to_mcp_server()` method
5. **Document your servers:** Help users understand what each server does by providing clear names

## API Reference

### Action Hook

```php
do_action_ref_array( 'acrossai_abilities_manager_get_mcp_servers', [ &$servers ] );
```

### Runtime Method

```php
use AcrossAI_Abilities_Manager\Runtime\Override_Applier;

$exposed = Override_Applier::should_expose_to_mcp_server( 
	string $slug,        // Full ability slug (e.g., 'provider/ability')
	string $server_id    // MCP server ID to check
): bool                 // True if ability should be exposed to server
```

## Related Documentation

- [README.md](../README.md) — Main plugin documentation
- [MCP Visibility Feature](../README.md#mcp-server-visibility) — User-facing feature documentation
- [Action Hooks](../README.md#action-hooks) — All available hooks
