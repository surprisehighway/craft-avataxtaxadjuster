# Avatax Tax Adjuster Changelog

## 1.0.6 -- 2018.5.18

### Added

* Send descriptions for products, discounts, and shipping line items.

### Improved

* Updated Avatax PHP SDK to v18.4.4.191."

## 1.0.5 -- 2017.12.22

### Added

* Connection Test in settings page.
* Full transaction logging (when enabled).
* Setting to enable/disable tax calculation.
* Setting to enable/disable document committing.
* AvaTax Address validation (when enabled).
* Refunds (AvaTax ReturnInvoice) for payment gateways that support refunds.

### Fixed

* Do not require a Shipping Method for initial tax calculation. (Shipping Address must still be set before initial calculation).

### Improved

* Default Tax Code now configurable.
* Default Shipping Tax Code now configurable.
* Customer code now uses email address to match Craft Commerce.
* Document code now uses the full Craft Commerce order number (prefixed).
* Updated documentation including product-level tax code field setup.


## 1.0.3 -- 2017.08.17

### Fixed

* Resolved createSalesInvoice error.

## 1.0.2 -- 2017.08.16

### Fixed

* Resolved concatenation syntax bug.

## 1.0.1 -- 2017.07.31

### Fixed

* Replaced missing source for /vendor/avalara/avataxclient.

## 1.0.0 -- 2017.06.07

* Initial release

Brought to you by [Rob Knecht](https://github.com/rmknecht) and [Surprise Highway](https://github.com/surprisehighway)
