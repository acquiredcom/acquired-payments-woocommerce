# Acquired.com for WooCommerce

## Commands

| Task | Command |
|---|---|
| PHP lint (WPCS) | `composer lint` |
| PHP lint, auto-fix | `composer fix` |
| Static analysis | `composer analyse` |
| Unit tests | `composer test` |
| JS lint | `npm run lint:js` |
| Build JS | `npx mix --production` |
| Translation template | `composer create-pot` |

CI (`.github/workflows/lint-and-test.yml`) runs JS lint, PHP lint and PHP tests on pushes and PRs to `main` and `staging`. PHPStan isn't in CI yet.

## Code layout

- `acquired-com-for-woocommerce.php`: entry file. Defines the `ACFW_*` constants, checks PHP/WooCommerce versions, loads `src/bootstrap.php`.
- `src/bootstrap.php`: PHP-DI container. New services are registered here (`autowire()` or a factory).
- `src/Observers/`: WordPress/WooCommerce hook registration, one observer per area. Exception: `PaymentGateway` registers its own hooks in `init_hooks()` (called from the constructor), following WooCommerce's payment gateway pattern.
- `src/Services/`: business logic. `src/Api/ApiClient.php`: Guzzle calls to the Acquired API. `src/Api/Response/`: one class per API response type.
- `src/WooCommerce/PaymentGateway.php`: the gateway. `src/WooCommerce/PaymentMethod.php`: block checkout integration.
- `assets/src/js/` is compiled by Laravel Mix into `assets/dist/js/`.

## Conventions

- WordPress Coding Standards (`phpcs.xml`). Every PHP file starts with `declare( strict_types = 1 );`, namespace `AcquiredComForWooCommerce\`.
- Every `src/` class has a matching test in `tests/Unit/` (PHPUnit 11 + Brain Monkey). Add or update the test with the change.
- Inline linter/analyser ignores name the rule only, no reason text: `// @phpstan-ignore if.alwaysFalse`.
- Copy the closest existing example before adding a class, method, setting or message.
- Plain string literals, no class constants (`'success'`, `'authorised'`, `'capture_transaction'`).
- Reuse the existing vocabulary: results `'success'` / `'error'` / `'invalid'`, log levels `'debug'` / `'error'`, admin notice types `'success'` / `'error'`.
- Docblocks: a one-line summary plus typed `@param` / `@return` / `@throws`, no descriptions. Inline comments only to explain a non-obvious flow, as in `OrderService::process_order()`.
- No one-use public helper methods.
- Settings: the default goes in the field's `'default'` and inline in `get_option( 'key', default )`. Getters are named after the option key.
- Log through `LoggerService::log()` only. WooCommerce requires that it writes only when the `debug_log` setting is on.

## Acquired API

- Code to what Acquired's docs state or what a test request showed. Don't add guards for hypothetical API behaviour; open questions go to Acquired.
- Response classes check only that required fields exist, no extra type or shape checks.
- Limits that look like bugs but come from Acquired API: the payment reference character set and 18-character length, the address field limits and regex in `CustomerService`, and partial refunds being allowed only from the next day.
- Pay by Bank isn't supported at the moment.

## PHPStan

- Config: `phpstan.neon`. Run `composer analyse` at the level set there and fix what it reports. Don't change the level or pass `--level`.
- `stubs/` holds declarations PHPStan can't find on its own, loaded through `scanFiles`. It's excluded from PHPCS.
- Plugin constants defined with a WordPress function call (`plugin_dir_path()` etc.) need a `define()` in `stubs/acfw-constants.php` and an entry in `dynamicConstantNames`. Constants defined with literals don't.
- Optional third-party plugins the code calls into (TeraWallet) get a stub in `stubs/`.

## Dependencies

- Update Composer and npm dependencies within their current major version only. Don't change a constraint to a new major version.
- After `composer update`, run `composer bump` so `composer.json` matches the installed versions.

## Releases

- Files that must not ship go in both `.distignore` (WordPress.org deploy) and the `zip -x` list in `.github/workflows/create-release.yml` (GitHub release zip).
- A version bump touches: the plugin header `Version` and `ACFW_VERSION` in `acquired-com-for-woocommerce.php`, `Stable tag` and the changelog in `readme.txt`, `package.json`, and `CHANGELOG.md`.
- Don't run `composer create-pot` or commit; the maintainer does both.
