# Convoca Media Suite — Testing Guide

## PHPUnit Tests (23 tests, 7 files)

### Test Files

| File | Tests | Coverage |
|------|-------|----------|
| `tests/Unit/TestPosterEngine.php` | 5 | Render, invalid activity, templates, JPG export |
| `tests/Unit/TestQRGenerator.php` | 3 | Generate, URL, cache invalidation |
| `tests/Unit/TestTemplateManager.php` | 6 | CRUD, get, validate, save |
| `tests/Unit/TestBlogPostManager.php` | 4 | Create, reuse, meta links, post type |
| `tests/Unit/TestMediaLogger.php` | 4 | Log entry, duration, limits, types |
| `tests/Unit/TestSocialScheduler.php` | 4 | Queue, scheduling, OAuth store/retrieve |

### Running PHPUnit Tests

```bash
cd wp-content/plugins/convoca-enroll
phpunit --configuration tests/Unit/phpunit.xml
```

### Prerequisites

- WordPress test suite configured
- `WP_TESTS_DIR` environment variable set
- Test database with WPUnitTestCase support

## Playwright E2E Tests

### Test Files

| File | Tests | Scenario |
|------|-------|----------|
| `tests/e2e/poster-generation.spec.js` | 5 | Metabox, poster generation, social checkboxes, blog post, dashboard |
| `tests/e2e/visual-regression.spec.js` | 25 | 3 templates × 3 formats + all 8 templates |

### Running E2E Tests

```bash
cd projects/convoca-ecosystem

# All tests
npx playwright test

# Media-specific
npm run test:media        # --grep @media
npm run test:poster       # poster-generation.spec.js
npm run test:visual       # visual-regression.spec.js

# Update visual reference snapshots
npm run snapshots:update  # UPDATE_SNAPSHOTS=1 ...
```

### Configuration

`playwright.config.js`:
```js
baseURL: 'http://localhost:8080',  // Change to your WP install
storageState: 'auth.json',         // Pre-saved admin session
```

### Generating auth state
```bash
npx playwright codegen --save-storage=auth.json http://localhost:8080/wp-admin
# Login as admin → Ctrl+C → auth.json created
```

### Prerequisites

- WordPress running at baseURL
- Activity post ID 100 exists with test data
- User `demo_admin` / `demo_pass`
- `UPDATE_SNAPSHOTS=1` on first run to create baseline images

### Visual Regression Workflow

1. First run: `UPDATE_SNAPSHOTS=1 npm run test:visual` → creates baseline PNGs
2. Subsequent runs: compares renders against baselines
3. On intentional visual changes: run with `UPDATE_SNAPSHOTS=1` to update baselines
4. Snapshots stored in: `tests/snapshots/`
