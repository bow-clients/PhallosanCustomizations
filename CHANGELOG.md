# Changelog – PhallosanCustomizations

## v0.0.5 – 2026-03-09

### Features

- **Auto-switch currency on country change**
  - When a user selects a country in the language widget dropdown, the currency is now automatically switched based on the `currency_country_rounding` table.
  - Added `getCurrencyIdForCountry()` method to `CountrySalesChannelMappingService` that reads country→currency mappings from the database.
  - `LanguageSwitchController::redirectByCountry()` now calls `ContextSwitchRoute::switchContext()` to persist the correct currency before redirecting.
  - Added `AbstractContextSwitchRoute` as a new dependency to `LanguageSwitchController`.

## v0.0.4 – 2026-03-06

### Bugfixes

- **Language Switch Controller:`languageDomainId` support**
  - The `redirectCustomer()` method in `LanguageSwitchController` now handles the `languageDomainId` parameter sent by the language dropdown.
  - Previously only `salesChannelDomainIdAndCountryId` (country switch) was supported, causing language-only switches to fail or redirect to the homepage.
  - When `languageDomainId` is present, the controller uses the domain ID directly and preserves the current country from the session.
  - Added `PhallosanConstants` import to the controller.
