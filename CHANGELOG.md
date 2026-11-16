# Changelog

All notable release changes to this project will be documented in this file.

## [2.2.0]
- Confirm order payments with the Acquired.com API when a redirect can't be verified with the signing key (missing, inactive or wrong key), instead of failing the order. An admin notice explains the signing key issue.
- Show signing key fields only for the selected environment.
- Clearer notice for invalid API credentials, including the reminder to whitelist the server IP address in the Acquired Hub.
- Send the currency to Acquired.com in uppercase.
- Remove Pay by Bank from the default gateway description.
- Redact date of birth and IP address in debug logs.
- Unslash the webhook hash and admin screen request values.
- Fix an extra space in the Acquired Hub links on the settings page.
- Fix typos and inconsistencies in notices and admin texts.
- Require WordPress 7.0. Tested up to WordPress 7.1.4 and WooCommerce 11.3.0.
- Add PHPStan, fix coding standards violations and update dependencies.

## [2.1.0]
-  Update to support the release of signing keys and multiple APP IDs.

## [2.0.0] Initial Release