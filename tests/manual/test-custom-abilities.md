# Manual End-to-End Test Guide for Custom Abilities

## Overview
This guide provides step-by-step instructions for testing the custom abilities feature of the AcrossAI Abilities Manager plugin. These tests can be executed directly in WordPress using REST API calls or the admin interface.

## Prerequisites
- WordPress 6.9+
- AcrossAI Abilities Manager plugin activated
- Administrator access
- curl installed (for REST API testing)
- Database access (for verification)

## Test Environment Setup

### 1. Enable REST API
Verify REST API is enabled:
```bash
# Should return {"index":"...","home":"..."}
curl https://local-site/wp-json/
```

### 2. Get Admin Authentication Token
```bash
# Create or get an admin user ID
curl -X POST https://local-site/wp-json/wp/v2/users/me \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

## Test Cases

### Test 1: Create Custom Ability via REST API (POST)
**Status**: ⏳ PENDING

**Description**: Create a custom ability through the REST API and verify it's stored in the database.

**Steps**:
1. Execute POST request:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{
    "label": "Test Ability One",
    "description": "A test ability for E2E testing",
    "input_schema": "{\"type\": \"object\", \"properties\": {\"test\": {\"type\": \"string\"}}}",
    "output_schema": "{\"type\": \"object\", \"properties\": {\"result\": {\"type\": \"string\"}}}",
    "status": "active",
    "category": "testing",
    "readonly": false,
    "destructive": false,
    "show_in_rest": true,
    "mcp_public": true
  }'
```

2. Expected response: 201 Created with ability data including:
   - `ability_slug`: "test-ability-001"
   - `label`: "Test Ability One"
   - `status`: "active"
   - `created_at`: timestamp
   - `updated_at`: timestamp

3. Verify in database:
```sql
SELECT * FROM wp_acrossai_custom_abilities WHERE ability_slug = 'test-ability-001';
```

**Expected Result**: ✅ PASS if ability appears in database with correct data.

---

### Test 2: Retrieve Custom Ability via REST API (GET)
**Status**: ⏳ PENDING

**Description**: Get a single custom ability by slug.

**Steps**:
1. Execute GET request:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -H "Content-Type: application/json" \
  -u admin:password
```

2. Expected response: 200 OK with ability data matching what was created.

3. Verify fields returned:
   - ability_slug
   - label
   - description
   - status
   - category
   - created_at
   - updated_at

**Expected Result**: ✅ PASS if all fields return correctly and match creation data.

---

### Test 3: List Custom Abilities via REST API (Paginated)
**Status**: ⏳ PENDING

**Description**: List all custom abilities with pagination support.

**Steps**:
1. Create 3 more test abilities (test-ability-002, test-ability-003, test-ability-004).

2. Execute GET with pagination:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?per_page=2&page=1" \
  -u admin:password
```

3. Verify response contains:
   - `items`: array of 2 abilities
   - `total`: 4 (or more if others exist)
   - `pages`: 2+
   - `page`: 1
   - `per_page`: 2

4. Request page 2:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?per_page=2&page=2" \
  -u admin:password
```

5. Verify page 2 returns different abilities.

**Expected Result**: ✅ PASS if pagination works correctly and abilities are returned in correct pages.

---

### Test 4: Search Abilities by Slug
**Status**: ⏳ PENDING

**Description**: Search custom abilities by partial slug match.

**Steps**:
1. Execute search query:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?search=ability-00" \
  -u admin:password
```

2. Verify response contains only abilities matching the search term.

3. Verify `total` reflects filtered count.

**Expected Result**: ✅ PASS if search returns only matching abilities.

---

### Test 5: Filter Abilities by Status
**Status**: ⏳ PENDING

**Description**: Filter abilities by active/draft/archived status.

**Steps**:
1. Create a draft ability:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/draft-ability-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Draft Test", "status": "draft"}'
```

2. Filter by active status:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?status=active" \
  -u admin:password
```

3. Verify only active abilities in response.

4. Filter by draft status:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?status=draft" \
  -u admin:password
```

5. Verify only draft abilities in response.

**Expected Result**: ✅ PASS if filtering by status works correctly.

---

### Test 6: Filter Abilities by Category
**Status**: ⏳ PENDING

**Description**: Filter abilities by category.

**Steps**:
1. Create abilities with different categories:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/writing-tool-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Writing Tool", "category": "writing"}'

curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/analysis-tool-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Analysis Tool", "category": "analysis"}'
```

2. Filter by category:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?category=writing" \
  -u admin:password
```

3. Verify only "writing" category abilities returned.

**Expected Result**: ✅ PASS if category filtering works.

---

### Test 7: Update Existing Ability (Upsert)
**Status**: ⏳ PENDING

**Description**: Update an ability without creating a new row.

**Steps**:
1. Get original ability timestamps:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -u admin:password
# Note the created_at and updated_at values
```

2. Update the ability:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{
    "label": "Updated Test Ability",
    "description": "Updated description"
  }'
```

3. Get updated ability:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -u admin:password
```

4. Verify:
   - Label changed to "Updated Test Ability"
   - `created_at` is unchanged
   - `updated_at` is newer
   - No new row created in database (still only 1 row with that slug)

**Expected Result**: ✅ PASS if update works without creating new rows.

---

### Test 8: Delete Custom Ability via REST API
**Status**: ⏳ PENDING

**Description**: Delete a custom ability.

**Steps**:
1. Verify ability exists:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -u admin:password
```

2. Delete it:
```bash
curl -X DELETE "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -u admin:password
```

3. Expected response: 200 OK with `{"deleted": true}`

4. Verify it's gone:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/test-ability-001" \
  -u admin:password
# Should return 404 Not Found
```

**Expected Result**: ✅ PASS if ability is deleted and no longer retrievable.

---

### Test 9: Runtime Registration - Active Ability
**Status**: ⏳ PENDING

**Description**: Verify active abilities are registered at runtime.

**Steps**:
1. Create an active ability:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/runtime-active-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Runtime Active Test", "status": "active"}'
```

2. In WordPress theme's functions.php or test script, check registration:
```php
<?php
// After wp_abilities_api_init hook fires
$has_ability = wp_has_ability( 'runtime-active-001' );
if ( $has_ability ) {
    echo "✅ Ability registered";
    $ability = wp_get_ability( 'runtime-active-001' );
    echo $ability->get_label(); // Should print "Runtime Active Test"
} else {
    echo "❌ Ability NOT registered";
}
?>
```

3. Or check via WP CLI:
```bash
wp eval 'echo wp_has_ability("runtime-active-001") ? "✅ Yes" : "❌ No";'
```

**Expected Result**: ✅ PASS if active ability is registered.

---

### Test 10: Runtime Registration - Draft Ability
**Status**: ⏳ PENDING

**Description**: Verify draft abilities are NOT registered at runtime.

**Steps**:
1. Create a draft ability:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/runtime-draft-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Runtime Draft Test", "status": "draft"}'
```

2. Check registration:
```php
<?php
$has_ability = wp_has_ability( 'runtime-draft-001' );
if ( ! $has_ability ) {
    echo "✅ Draft ability NOT registered (correct)";
} else {
    echo "❌ Draft ability WAS registered (incorrect)";
}
?>
```

**Expected Result**: ✅ PASS if draft abilities are NOT registered.

---

### Test 11: Activate Draft Ability
**Status**: ⏳ PENDING

**Description**: Change draft to active and verify it becomes registered.

**Steps**:
1. Update the draft ability to active:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/runtime-draft-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"status": "active"}'
```

2. Check registration:
```php
<?php
$has_ability = wp_has_ability( 'runtime-draft-001' );
if ( $has_ability ) {
    echo "✅ Ability now registered after activation";
} else {
    echo "❌ Ability still not registered";
}
?>
```

**Expected Result**: ✅ PASS if ability becomes registered after status change.

---

### Test 12: Metadata Flags - readonly
**Status**: ⏳ PENDING

**Description**: Verify readonly flag is applied to ability.

**Steps**:
1. Create ability with readonly=true:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/readonly-test-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Readonly Test", "readonly": true, "status": "active"}'
```

2. Check metadata:
```php
<?php
$ability = wp_get_ability( 'readonly-test-001' );
if ( $ability->is_readonly() ) {
    echo "✅ Readonly flag applied";
} else {
    echo "❌ Readonly flag NOT applied";
}
?>
```

**Expected Result**: ✅ PASS if readonly flag is properly applied.

---

### Test 13: Metadata Flags - destructive
**Status**: ⏳ PENDING

**Description**: Verify destructive flag is applied.

**Steps**:
1. Create ability with destructive=true:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/destructive-test-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Destructive Test", "destructive": true, "status": "active"}'
```

2. Check metadata:
```php
<?php
$ability = wp_get_ability( 'destructive-test-001' );
if ( $ability->is_destructive() ) {
    echo "✅ Destructive flag applied";
} else {
    echo "❌ Destructive flag NOT applied";
}
?>
```

**Expected Result**: ✅ PASS if destructive flag is applied.

---

### Test 14: Metadata Flags - show_in_rest
**Status**: ⏳ PENDING

**Description**: Verify show_in_rest flag is applied.

**Steps**:
1. Create ability with show_in_rest=true:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/rest-test-001" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "REST Test", "show_in_rest": true, "status": "active"}'
```

2. Check metadata:
```php
<?php
$ability = wp_get_ability( 'rest-test-001' );
if ( $ability->should_show_in_rest() ) {
    echo "✅ show_in_rest flag applied";
} else {
    echo "❌ show_in_rest flag NOT applied";
}
?>
```

**Expected Result**: ✅ PASS if show_in_rest flag is applied.

---

### Test 15: Validation Error - Invalid Slug
**Status**: ⏳ PENDING

**Description**: Verify invalid slugs are rejected.

**Steps**:
1. Try to create ability with invalid slug (special characters):
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/invalid@slug!" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Invalid Slug Test"}'
```

2. Expected response: 400 Bad Request with error message about invalid slug.

3. Verify ability was NOT created:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?search=invalid" \
  -u admin:password
# Should return empty list
```

**Expected Result**: ✅ PASS if validation rejects invalid slug.

---

### Test 16: Validation Error - Missing Label
**Status**: ⏳ PENDING

**Description**: Verify label is required.

**Steps**:
1. Try to create ability without label:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/no-label-test" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{}'
```

2. Expected response: 400 Bad Request with error about missing label.

**Expected Result**: ✅ PASS if label validation works.

---

### Test 17: Validation Error - Invalid JSON Schema
**Status**: ⏳ PENDING

**Description**: Verify invalid JSON schemas are rejected.

**Steps**:
1. Try to create ability with invalid JSON schema:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/bad-json-test" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Bad JSON Test", "input_schema": "not-valid-json"}'
```

2. Expected response: 400 Bad Request with error about invalid JSON.

**Expected Result**: ✅ PASS if JSON schema validation works.

---

### Test 18: Validation Error - Invalid Status
**Status**: ⏳ PENDING

**Description**: Verify invalid status values are rejected.

**Steps**:
1. Try to create ability with invalid status:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/bad-status-test" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Bad Status Test", "status": "invalid_status"}'
```

2. Expected response: 400 Bad Request with error about invalid status.

3. Valid statuses should be: active, draft, archived

**Expected Result**: ✅ PASS if status validation works.

---

### Test 19: Permission Check - Non-Admin User
**Status**: ⏳ PENDING

**Description**: Verify non-admin users cannot create abilities.

**Steps**:
1. Get subscriber user credentials (or create one).

2. Try to create ability as subscriber:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/subscriber-test" \
  -H "Content-Type: application/json" \
  -u subscriber:password \
  -d '{"label": "Subscriber Test"}'
```

3. Expected response: 403 Forbidden

4. Try to list abilities:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities" \
  -u subscriber:password
```

5. Expected response: 403 Forbidden

**Expected Result**: ✅ PASS if non-admins get 403 Forbidden.

---

### Test 20: Ordering by Slug (ASC/DESC)
**Status**: ⏳ PENDING

**Description**: Verify ordering works correctly.

**Steps**:
1. Create abilities with distinguishable names:
   - z-ability
   - a-ability
   - m-ability

2. Order by slug ASC:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?orderby=ability_slug&order=ASC" \
  -u admin:password
```

3. Verify first item is "a-ability", last is "z-ability".

4. Order by slug DESC:
```bash
curl "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities?orderby=ability_slug&order=DESC" \
  -u admin:password
```

5. Verify first item is "z-ability", last is "a-ability".

**Expected Result**: ✅ PASS if ordering works in both directions.

---

### Test 21: Admin UI - Create Ability Form
**Status**: ⏳ PENDING

**Description**: Create ability via admin form.

**Steps**:
1. Navigate to Tools > Add New Ability in WordPress admin.

2. Fill in the form:
   - Ability Slug: "admin-form-test"
   - Label: "Admin Form Test"
   - Description: "Testing form creation"
   - Status: Active
   - Category: Testing

3. Click "Save Ability".

4. Verify:
   - Success message displayed
   - Redirected to list screen
   - New ability appears in list

5. Verify in database:
```sql
SELECT * FROM wp_acrossai_custom_abilities WHERE ability_slug = 'admin-form-test';
```

**Expected Result**: ✅ PASS if admin form creates ability correctly.

---

### Test 22: Admin UI - Edit Ability
**Status**: ⏳ PENDING

**Description**: Edit ability from admin list.

**Steps**:
1. Navigate to Tools > Ability Manager.

2. Find a test ability in the list.

3. Click "Edit" action.

4. Modify the label and description.

5. Click "Save Ability".

6. Verify:
   - Changes appear in list
   - Database updated

**Expected Result**: ✅ PASS if editing works via admin UI.

---

### Test 23: Admin UI - Delete Ability
**Status**: ⏳ PENDING

**Description**: Delete ability from admin list.

**Steps**:
1. Navigate to Tools > Ability Manager.

2. Find a test ability in the list.

3. Click "Delete" action.

4. Confirm deletion.

5. Verify:
   - Ability no longer in list
   - Database row deleted

**Expected Result**: ✅ PASS if deletion works via admin UI.

---

### Test 24: Admin UI - Search & Filter
**Status**: ⏳ PENDING

**Description**: Test search and filtering in list.

**Steps**:
1. Create multiple test abilities with different statuses and categories.

2. Navigate to Tools > Ability Manager.

3. Use search box to find abilities by partial slug.

4. Verify results are filtered correctly.

5. Use status filter dropdown.

6. Verify only matching status shown.

7. Use category filter.

8. Verify only matching category shown.

**Expected Result**: ✅ PASS if search and filters work in admin UI.

---

### Test 25: Duplicate Ability - Same Slug Creates New Row or Updates?
**Status**: ⏳ PENDING

**Description**: Verify behavior when creating ability with existing slug.

**Steps**:
1. Create ability "duplicate-test":
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/duplicate-test" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "First Label"}'
```

2. Note the database ID and created_at.

3. Create again with same slug:
```bash
curl -X POST "https://local-site/wp-json/acrossai-abilities-manager/v1/custom-abilities/duplicate-test" \
  -H "Content-Type: application/json" \
  -u admin:password \
  -d '{"label": "Second Label"}'
```

4. Check database - should be only 1 row (upsert behavior):
```sql
SELECT COUNT(*) FROM wp_acrossai_custom_abilities WHERE ability_slug = 'duplicate-test';
```

5. Verify label updated to "Second Label".

6. Verify ID is same (same row, updated).

**Expected Result**: ✅ PASS if upsert behavior works correctly.

---

## Database Verification Queries

### View all custom abilities
```sql
SELECT * FROM wp_acrossai_custom_abilities ORDER BY created_at DESC;
```

### Count abilities by status
```sql
SELECT status, COUNT(*) FROM wp_acrossai_custom_abilities GROUP BY status;
```

### Count abilities by category
```sql
SELECT category, COUNT(*) FROM wp_acrossai_custom_abilities GROUP BY category;
```

### Find recently created abilities
```sql
SELECT ability_slug, label, status, created_at FROM wp_acrossai_custom_abilities 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY created_at DESC;
```

### Check for duplicate slugs (should be none)
```sql
SELECT ability_slug, COUNT(*) as count FROM wp_acrossai_custom_abilities 
GROUP BY ability_slug HAVING count > 1;
```

---

## Summary

### Test Results Table

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 1 | Create via REST API | ⏳ | |
| 2 | Retrieve via REST API | ⏳ | |
| 3 | List with Pagination | ⏳ | |
| 4 | Search by Slug | ⏳ | |
| 5 | Filter by Status | ⏳ | |
| 6 | Filter by Category | ⏳ | |
| 7 | Update (Upsert) | ⏳ | |
| 8 | Delete via REST API | ⏳ | |
| 9 | Runtime - Active Ability | ⏳ | |
| 10 | Runtime - Draft Ability | ⏳ | |
| 11 | Activate Draft | ⏳ | |
| 12 | Metadata - readonly | ⏳ | |
| 13 | Metadata - destructive | ⏳ | |
| 14 | Metadata - show_in_rest | ⏳ | |
| 15 | Validation - Invalid Slug | ⏳ | |
| 16 | Validation - Missing Label | ⏳ | |
| 17 | Validation - Invalid JSON | ⏳ | |
| 18 | Validation - Invalid Status | ⏳ | |
| 19 | Permission - Non-Admin | ⏳ | |
| 20 | Ordering | ⏳ | |
| 21 | Admin UI - Create | ⏳ | |
| 22 | Admin UI - Edit | ⏳ | |
| 23 | Admin UI - Delete | ⏳ | |
| 24 | Admin UI - Search & Filter | ⏳ | |
| 25 | Duplicate Slug (Upsert) | ⏳ | |

### Final Checklist

- ✅ All 25 test cases documented
- ✅ Step-by-step instructions provided
- ✅ Expected responses documented
- ✅ Database queries provided
- ✅ REST API calls provided
- ✅ Admin UI steps documented
- ✅ Validation tests included
- ✅ Permission tests included
- ✅ Runtime registration tests included
- ✅ Metadata flag tests included
- ✅ Search & Filter tests included
- ✅ CRUD operations tests included

## Notes

- Mark test as ✅ PASS when successful
- Mark as ❌ FAIL with notes if not
- Update this document as you test
- Clean up test data after running tests
