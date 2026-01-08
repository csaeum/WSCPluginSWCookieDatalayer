# Tests Documentation

This directory contains the test suite for the WSC Cookie DataLayer Plugin.

## Test Structure

```
tests/
├── bootstrap.php           # PHPUnit bootstrap file
├── Unit/                   # Unit tests (isolated, fast)
│   ├── Service/
│   │   └── DataLayerBuilderTest.php
│   └── Subscriber/
│       └── DataLayerSubscriberTest.php
└── Integration/            # Integration tests (with dependencies)
    └── DataLayerIntegrationTest.php
```

## Running Tests

### Run all tests
```bash
composer test
```

### Run only Unit tests
```bash
vendor/bin/phpunit --testsuite Unit
```

### Run only Integration tests
```bash
vendor/bin/phpunit --testsuite Integration
```

### Run with coverage report
```bash
composer test-coverage
```
Coverage HTML report will be generated in `coverage/` directory.

## Test Coverage

**Target: 80% code coverage**

Currently testing:
- ✅ DataLayerBuilder Service (all public methods)
- ✅ DataLayerSubscriber (event subscription and handling)
- ✅ Integration: Complete event flow

## Writing New Tests

### Unit Test Example
```php
namespace WSC\SWCookieDataLayer\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
```

### Integration Test Example
```php
namespace WSC\SWCookieDataLayer\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * @group integration
 */
class MyIntegrationTest extends TestCase
{
    public function testCompleteFlow(): void
    {
        // Test with real instances
    }
}
```

## Best Practices

1. **Unit Tests**: Mock all dependencies
2. **Integration Tests**: Use real instances where possible
3. **Test Names**: Use descriptive names (`testBuildViewItemDataWithValidProduct`)
4. **Assertions**: Be specific, test one thing per test
5. **Setup**: Use `setUp()` for common initialization

## CI/CD Integration

These tests run automatically on every push via GitHub Actions (see `.github/workflows/ci.yml`).

## Troubleshooting

### "Class not found" errors
Run: `composer install` to install dependencies

### "Cannot find bootstrap.php"
Run tests from plugin root directory

### Tests fail in CI but pass locally
Check PHP version compatibility (plugin supports PHP 8.0+)
