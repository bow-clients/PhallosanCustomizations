# Changelog – PhallosanCustomizations

## v0.0.4 – 2026-03-06

### Bugfixes

- **Language Switch Controller:`languageDomainId` support**
  - The `redirectCustomer()` method in `LanguageSwitchController` now handles the `languageDomainId` parameter sent by the language dropdown.
  - Previously only `salesChannelDomainIdAndCountryId` (country switch) was supported, causing language-only switches to fail or redirect to the homepage.
  - When `languageDomainId` is present, the controller uses the domain ID directly and preserves the current country from the session.
  - Added `PhallosanConstants` import to the controller.
