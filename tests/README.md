# AcrossAI Abilities Manager - End-to-End Tests

## Overview

This directory contains comprehensive end-to-end tests for the AcrossAI Abilities Manager plugin's custom abilities feature. The tests validate the complete workflow from creation through registration, execution, and deletion.

## Test Files

- **`E2E_Custom_Abilities_Test.php`** - Automated PHPUnit tests (25 test cases)
- **`manual/test-custom-abilities.md`** - Manual testing guide with step-by-step instructions
- **`TEST_RESULTS.md`** - Test documentation and results tracker

## Running the Tests

### Prerequisites

- WordPress 6.9+
- PHP 8.0+
- PHPUnit 8.5+ or 9.6+
- MySQL/MariaDB for test database

### Automated Tests (PHPUnit)

```bash
# Run all tests
composer run test

# Run just the E2E tests
./vendor/bin/phpunit tests/E2E_Custom_Abilities_Test.php

# Run with verbose output
./vendor/bin/phpunit tests/E2E_Custom_Abilities_Test.php -v

# Run specific test
./vendor/bin/phpunit --filter test_create_custom_ability_via_rest_api tests/E2E_Custom_Abilities_Test.php
```

### Manual Tests

For manual testing, see `tests/manual/test-custom-abilities.md` for:
- Step-by-step REST API testing instructions
- Admin UI testing procedures
- Database verification queries
- Expected results for each test case

## Test Coverage

### Automated Tests (25 test cases)

The PHPUnit test suite covers:

1. **Database Operations** (7 tests)
   - Create ability via REST API
   - Retrieve ability
   - List with pagination
   - Update (upsert) ability
   - Delete ability
   - Duplicate slug behavior
   - Timestamp generation

2. **Runtime Registration** (4 tests)
   - Active ability registration
   - Draft ability not registered
   - Activate draft ability
   - Metadata application (readonly, destructive, show_in_rest)

3. **Validation** (6 tests)
   - Invalid slug rejection
   - Missing label detection
   - Invalid JSON schema detection
   - Valid schema acceptance
   - Invalid status rejection
   - Valid slug format

4. **Search & Filter** (5 tests)
   - Search by slug
   - Filter by status
   - Filter by category
   - Ordering by various columns
   - Pagination

5. **Security** (1 test)
   - Permission checks for non-admin users

6. **Data Integrity** (1 test)
   - Custom metadata storage

## Files Tested

- ✅ `includes/Database/Repository.php` - CRUD operations
- ✅ `includes/Database/Schema.php` - Database schema
- ✅ `includes/Validation/Ability_Validator.php` - Validation logic
- ✅ `includes/REST/Custom_Abilities_Controller.php` - REST endpoints
- ✅ `includes/Runtime/Override_Applier.php` - Runtime registration

## Test Execution

### Setup

1. Ensure WordPress test database exists:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS wordpress_test"
```

2. Create `wp-tests-config.php` in plugin root with proper database credentials.

3. Install dependencies:
```bash
composer install
```

### Run Tests

```bash
# From plugin directory
composer run test

# Or directly with phpunit
./vendor/bin/phpunit
```

## Expected Results

All tests should pass with output like:

```
PHPUnit 9.6.34 by Sebastian Bergmann and contributors.

............................ 25 / 25 (100%)

Time: 15.234 seconds, Memory: 45.50 MB

OK (25 tests, 150+ assertions)
```

## Test Organization

Each test:
- ✅ Sets up required test data
- ✅ Executes the test scenario
- ✅ Asserts expected behavior
- ✅ Cleans up after itself

### Test Isolation

- Database is cleaned between tests via `tear_down()`
- Object cache is flushed to ensure fresh data
- User roles are properly set per test

## Debugging Failed Tests

If a test fails:

1. Check the error message for the specific assertion that failed
2. Run the test in verbose mode: `-v`
3. Check database state manually if needed
4. Review the test's `set_up()` and `tear_down()` methods
5. Examine the specific REST endpoint or repository method being tested

## Continuous Integration

These tests can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions
- name: Run PHPUnit Tests
  run: composer run test
```

## Adding New Tests

To add new test cases:

1. Create a new test method in `E2E_Custom_Abilities_Test.php`
2. Follow naming convention: `test_<feature>_<scenario>`
3. Use existing test patterns (set up, execute, assert, clean up)
4. Add documentation in `TEST_RESULTS.md`
5. Ensure test is isolated and doesn't depend on other tests
6. Run linter: `composer run lint`

## Troubleshooting

### "Cannot open file /path/to/bootstrap.php"
- Verify `wp-phpunit/wp-phpunit/includes/bootstrap.php` exists
- Check composer dependencies are installed: `composer install`

### "Connection refused" or database errors
- Ensure MySQL/MariaDB is running
- Check `wp-tests-config.php` has correct credentials
- Verify test database was created
- Check `DB_HOST` is correct (localhost or 127.0.0.1)

### Tests timeout
- Increase PHPUnit timeout in `phpunit.xml`
- Check database server is responsive
- Reduce test batch size if running many tests

## Performance

Typical test execution:
- Full suite: 15-30 seconds
- Per test: 0.5-2 seconds
- Database operations: 5-10ms each

## Security Considerations

⚠️ **Important**: These tests use a separate test database. Never run tests against production data.

Test database should:
- ✅ Be isolated from production
- ✅ Use test-specific credentials
- ✅ Allow truncation/deletion
- ✅ Be cleaned up after tests

## Contributing

When contributing test improvements:

1. Maintain backward compatibility with existing tests
2. Add tests for new features
3. Ensure all tests pass before submitting PR
4. Follow WordPress coding standards (run linter)
5. Document test purpose clearly
6. Keep tests independent and isolated

## Resources

- [PHPUnit Documentation](https://phpunit.de/)
- [WordPress Plugin Testing](https://developer.wordpress.org/plugins/testing/)
- [WP PHPUnit](https://github.com/WordPress/wordpress-develop)
- [WordPress Abilities API](https://github.com/WordPress/wordpress-develop/tree/trunk/src/wp-includes/abilities)
