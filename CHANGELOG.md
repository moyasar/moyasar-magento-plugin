# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), 
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 5.3.0 - 2026-06-07

### Added

- Support UnionPay payment method

## 5.2.5 - 2026-08-05

### Fixed

- Fixed credit card 3D Secure / OTP modal getting stuck on a loading state. The full-screen loader is now stopped when the OTP modal opens, and completion is detected via the iframe `load` event instead of polling the iframe URL, which also stops the cross-origin `SecurityError` console spam.

## 5.2.4 - 2026-04-05

### Fixed

- Update currency handling and payment amount calculations

## 5.2.3 - 2026-03-05

### Fixed

- Fixed STC pay if user clicks back

## 5.2.2 - 2025-10-03

### Fixed

- Fixed an issue with get/set AdditionalInformation in the order
- Fixed an issue with webhook endpoind

## 5.2.1 - 2025-07-24

### Added
- Samsung Pay Country Code

## 5.2.0 - 2025-07-07

### Added

- Apple Pay: Support Apple Pay on the web.

## 5.1.4 - 2025-05-27

### Fixed
- Reworked the modal popup

## 5.1.3 - 2025-03-06

## Changed
- Add Pay and currency to the credit cart submits button

### Fixed
- Apply discount if moyasar coupon is used

## 5.1.2 - 2025-01-26
### Changed
-  Force ```isPlaceOrderActionAllowed``` to true to avoid billing address validation


## 5.1.1 - 2025-01-20
### Fixed
- Fixed an issue with the Samsung Pay payment title.

## 5.1.0 - 2025-01-18
### Added
- Input field for customizing the store name in Apple Pay.
- Samsung Pay integration as a new payment method.


## 1.0.0 - unknown

- First release 🎉.
