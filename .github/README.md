# CI/CD Documentation

This directory contains GitHub Actions workflows for automated testing, quality checks, and releases.

## Workflows

### 1. CI Workflow (`ci.yml`)

**Triggers:** Every push to `main` or `develop` branch, and all pull requests

**Jobs:**

#### Code Quality Checks
- **PHP-CS-Fixer**: PSR-12 compliance check
- **PHPStan**: Static analysis (Level 5)
- **PHP Version**: 8.1

#### Tests
- **PHPUnit**: Runs all unit and integration tests
- **PHP Versions**: 8.0, 8.1, 8.2, 8.3 (matrix build)
- **Coverage**: Generated on PHP 8.1, uploaded to Codecov

#### Plugin Validation
- Validates `composer.json` structure
- Security audit (composer audit)
- Plugin file structure check
- File permission check

**Status:** ✅ All checks must pass before merge

### 2. Release Workflow (`release.yml`)

**Triggers:** When a version tag is pushed (e.g., `v1.0.0`)

**Jobs:**

#### Create GitHub Release
1. **Build**: Install production dependencies
2. **Archive**: Create ZIP file for Shopware installation
3. **Changelog**: Auto-generate from git commits
4. **Release**: Create GitHub release with ZIP attachment
5. **Prerelease**: Versions with `-` suffix (e.g., `v1.0.0-beta`) are marked as prerelease

**Excluded from ZIP:**
- `.git*` files
- `node_modules`
- `tests/`
- `coverage/`
- Development config files (phpunit.xml, phpstan.neon, etc.)
- `.github/`
- `*.md` documentation

## Running Checks Locally

Before pushing, run these commands to ensure CI will pass:

```bash
# Install dependencies
composer install

# Run all CI checks at once
composer ci

# Or run individually:
composer cs-check   # Code style check
composer phpstan    # Static analysis
composer test       # PHPUnit tests
```

## Creating a Release

### Automatic Release

```bash
# Create and push a tag
git tag v1.0.0
git push origin v1.0.0

# GitHub Actions will automatically:
# - Run all CI checks
# - Create release ZIP
# - Generate changelog
# - Create GitHub release
```

### Version Naming

Follow [Semantic Versioning](https://semver.org/):

- **Major:** `v1.0.0` - Breaking changes
- **Minor:** `v1.1.0` - New features (backwards compatible)
- **Patch:** `v1.0.1` - Bug fixes
- **Prerelease:** `v1.0.0-beta`, `v1.0.0-rc1` - Testing versions

## CI Configuration Files

| File | Purpose |
|------|---------|
| `.github/workflows/ci.yml` | Main CI pipeline |
| `.github/workflows/release.yml` | Release automation |
| `.php-cs-fixer.php` | Code style rules (PSR-12) |
| `phpstan.neon` | Static analysis config |
| `phpunit.xml.dist` | PHPUnit configuration |
| `composer.json` | Scripts and dependencies |

## Badges

Add these to your main README.md:

```markdown
![CI](https://github.com/csaeum/WSCPluginSWCookieDatalayer/workflows/CI/badge.svg)
![Tests](https://github.com/csaeum/WSCPluginSWCookieDatalayer/workflows/Tests/badge.svg)
[![codecov](https://codecov.io/gh/csaeum/WSCPluginSWCookieDatalayer/branch/main/graph/badge.svg)](https://codecov.io/gh/csaeum/WSCPluginSWCookieDatalayer)
![PHP Version](https://img.shields.io/badge/PHP-8.0%20%7C%208.1%20%7C%208.2%20%7C%208.3-blue)
![Shopware](https://img.shields.io/badge/Shopware-6.5%20%7C%206.6%20%7C%206.7-blue)
[![License](https://img.shields.io/github/license/csaeum/WSCPluginSWCookieDatalayer)](LICENSE)
```

## Troubleshooting

### CI Fails on PHP-CS-Fixer

**Fix locally:**
```bash
composer cs-fix
git add .
git commit -m "Fix: Code style"
```

### CI Fails on PHPStan

**Run locally to see errors:**
```bash
composer phpstan
```

**Common fixes:**
- Add type hints
- Fix undefined properties/methods
- Add PHPDoc comments

### Tests Fail in CI but Pass Locally

**Check:**
- PHP version differences (CI runs on 8.0, 8.1, 8.2, 8.3)
- Missing dependencies (run `composer install`)
- Environment-specific issues

### Release Not Created

**Verify:**
- Tag format is correct (`v1.0.0`)
- Tag is pushed to GitHub (`git push origin v1.0.0`)
- CI checks passed before tagging
- GitHub Actions have proper permissions

## Security

- **Secrets**: No secrets required for CI (only GitHub Token for releases)
- **Dependencies**: Audited automatically via `composer audit`
- **Permissions**: Workflows use minimal required permissions

## Performance

- **Caching**: Composer dependencies cached (speeds up builds)
- **Matrix**: Tests run in parallel across PHP versions
- **Duration**: ~3-5 minutes for full CI pipeline

## Future Improvements

- [ ] E2E tests with Cypress/Playwright
- [ ] Automatic Shopware Store upload
- [ ] Dependency update automation (Dependabot)
- [ ] Performance benchmarks
- [ ] Code quality trends
