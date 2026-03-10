# Changelog – PhallosanCustomizations

## v0.0.7 – 2026-03-10

### Bugfixes

- **Currency switch now works across Sales Channel redirects**
  - Previously currency only switched correctly within the same Sales Channel. Cross-SC redirects (e.g. AT→AU) lost the currency.
  - `LanguageSwitchController`: Now sets a short-lived `phallosan_target_currency` cookie (2 min, cross-domain) when redirecting to a different Sales Channel.
  - New `CurrencySwitchSubscriber`: Listens on `StorefrontRenderEvent` (high priority), reads the cookie on the target domain, switches currency via `ContextSwitchRoute`, and clears the cookie in `KernelEvents::RESPONSE`.
  - Within same SC, currency is still switched directly via `contextSwitchRoute->switchContext()`.

## v0.0.6 – 2026-03-10

### Features

- **Static country→currency mapping (`DEFAULT_CURRENCY_BY_COUNTRY`)**
  - Replaced DB-based `currency_country_rounding` lookup with a static mapping in `CountrySalesChannelMappingService`.
  - Currencies: EUR (51 EU countries), CHF (CH, LI), AUD (AU), JPY (JP), GBP (GB, IO, VG, GG, IM, JE). All other countries default to USD.
  - Added region-based fallback: if the mapped currency is not available in the target Sales Channel, falls back to EUR (EU), USD (Asia/World).
  - `getCurrencyIdForCountry()` now resolves the currency ISO to a currency ID via `sales_channel_currency` table and validates availability.
  - Added `getCurrencyIsoForCountry()` helper method.

## v0.0.5 – 2026-03-09

### Features

- **Auto-switch currency on country change**
  - `LanguageSwitchController::redirectByCountry()` now calls `ContextSwitchRoute::switchContext()` to persist the correct currency before redirecting.
  - Added `AbstractContextSwitchRoute` as a new dependency to `LanguageSwitchController`.

## v0.0.4 – 2026-03-06

### Bugfixes

- **Language Switch Controller:`languageDomainId` support**
  - The `redirectCustomer()` method in `LanguageSwitchController` now handles the `languageDomainId` parameter sent by the language dropdown.
  - Previously only `salesChannelDomainIdAndCountryId` (country switch) was supported, causing language-only switches to fail or redirect to the homepage.
  - When `languageDomainId` is present, the controller uses the domain ID directly and preserves the current country from the session.
  - Added `PhallosanConstants` import to the controller.
