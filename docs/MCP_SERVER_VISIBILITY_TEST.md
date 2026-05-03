# MCP Server Visibility Integration Test

This document describes how to test the MCP server visibility control feature that was just implemented.

## Prerequisites

- ✅ AcrossAI Abilities Manager plugin activated
- ✅ MCP Adapter plugin activated
- ✅ Default MCP server running at `http://localhost:10219/wp-json/mcp/mcp-adapter-default-server`

## Test Steps

### 1. Verify Server Discovery

1. Navigate to **Tools → Ability Manager** in WordPress admin
2. Click **Edit** on any provider ability (e.g., "Image Import")
3. Scroll to the **MCP Visibility** section
4. Select the radio button: **"Allow in specific MCP servers"**
5. **Expected Result**: A server selector appears below with "mcp-adapter-default-server" listed

### 2. Test "Disable for MCP" Option

1. In the same edit form, select: **"Disable for MCP"**
2. Click **Save**
3. **Expected Result**: 
   - The ability is saved with `mcp_public = null` and `mcp_servers = null`
   - No MCP server has access to this ability

### 3. Test "Allow in all MCP servers" Option

1. Select: **"Allow in all MCP servers"**
2. Click **Save**
3. **Expected Result**:
   - The ability is saved with `mcp_public = true` and `mcp_servers = null`
   - All MCP servers have access to this ability

### 4. Test "Allow in specific servers" Option

1. Select: **"Allow in specific MCP servers"**
2. Check the checkbox next to "MCP Adapter Default Server"
3. Click **Save**
4. **Expected Result**:
   - The ability is saved with `mcp_public = false` and `mcp_servers = ["mcp-adapter-default-server"]`
   - Only the selected server has access

### 5. Verify Edit Mode Shows Saved Servers

1. Save an ability with specific servers selected
2. Navigate away from the edit screen
3. Click **Edit** on the same ability again
4. **Expected Result**: The previously selected servers are still checked

### 6. Test with Multiple Servers (Future)

When additional MCP servers are registered (e.g., another adapter instance):
1. The server selector should show all available servers
2. Multiple servers can be selected at once
3. The ability will only be exposed to selected servers

## Database Verification

To verify the data is stored correctly:

```bash
# SSH into WordPress or use a database client
mysql wordpress

SELECT slug, mcp_public, mcp_servers FROM wp_acrossai_abilities_overwrite WHERE mcp_servers IS NOT NULL LIMIT 1\G
```

Expected output for specific servers:
```
slug: ai/image-import
mcp_public: 0
mcp_servers: ["mcp-adapter-default-server"]
```

## Runtime Verification

To verify runtime filtering works, check the MCP server's ability discovery:

```bash
# Query the MCP endpoint to see which abilities are exposed
curl -s http://localhost:10219/wp-json/mcp/mcp-adapter-default-server/discover \
  -H "Content-Type: application/json" \
  | jq '.resources[] | {name, description}' | head -20
```

Abilities with `"Allow in specific servers"` set to only this server should appear. Abilities with "Disable for MCP" should not.

## Troubleshooting

### Server selector is empty or hidden

**Symptoms**: Clicking "Allow in specific MCP servers" shows "No MCP servers available"

**Solution**:
- Verify MCP Adapter plugin is activated
- Check that the MCP server is running (visible in page source or REST response)
- Try deactivating and reactivating both plugins
- Check browser console for JavaScript errors

### Changes not saving

**Symptoms**: Server selections don't persist after save

**Cause**: Nonce validation or form submission issue

**Solution**:
- Check that form nonce is valid
- Verify ability slug is valid format (e.g., "provider/slug")
- Check browser console for form submission errors

### Server IDs showing as empty

**Symptoms**: Checkboxes appear but no labels

**Cause**: Server name (label) not provided correctly

**Solution**:
- Check MCP Adapter's `get_server_name()` method returns valid string
- Verify server configuration has a valid `server_name` parameter

## Files Modified

- **mcp-adapter/includes/Core/McpAdapter.php**: Added `register_servers_with_abilities_manager()` method
- **acrossai-abilities-manager/docs/MCP_SERVER_INTEGRATION.md**: Integration guide created
- **acrossai-abilities-manager/README.md**: Enhanced hook documentation
- **acrossai-abilities-manager/includes/Admin/Edit_Screen.php**: Fixed server selector visibility

## Next Steps

After testing:
1. Try adding or removing abilities from specific servers
2. Verify the MCP server respects the visibility settings when discovering abilities
3. Test with additional MCP servers when available
4. Document any edge cases found
