# AvaTax Tax Adjuster plugin for Craft Commerce

Calculates tax rates with Avalara AvaTax

This plugin is in beta and bugs may be present. Please document any issues you encounter at our [Github Issues](https://github.com/surprisehighway/craft-avataxtaxadjuster/issues) page.


## Installation

To install the AvaTax Tax Adjuster plugin, follow these steps:

1. Download & unzip the file and place the `avataxtaxadjuster` directory into your `craft/plugins` directory
2.  -OR- do a `git clone https://github.com/surprisehighway/craft-avataxtaxadjuster.git avataxtaxadjuster` directly into your `craft/plugins` folder.  You can then update it with `git pull`
3.  -OR- install with Composer via `composer require surprisehighway/avataxtaxadjuster`
4. Install plugin in the Craft Control Panel under Settings > Plugins
5. The plugin folder should be named `avataxtaxadjuster` for Craft to see it. GitHub recently started appending `-master` (the branch name) to the name of the folder for zip file downloads.

AvaTax Tax Adjuster works on Craft 2.6.x

## AvaTax Tax Adjuster Overview

Calculate and add sales tax to an order's base tax using Avalara's AvaTax service.

## Configuring AvaTax Tax Adjuster

1. Copy `config.php` from the `avataxtaxadjuster` directory to your craft/config folder and rename it to `avataxtaxadjuster.php`
2. Specify a valid origin address within the `shipFrom` array.
3. Specify a valid [Avalara Tax Code](https://taxcode.avatax.avalara.com/) for the `defaultTaxCode` value to use as the default tax code for your products.
4. Specify a valid [Avalara Tax Code](https://taxcode.avatax.avalara.com/) for the `defaultShippingCode` value to use as the tax code for shipping charges.

## Configuring AvaTax Account Connection

1. Visit the settings page at `Settings > Avatax Tax Adjuster`
2. Enter your the Account ID, License Key, and Company code credentials for each environment.
3. Selecting *Sandbox* or *Production* will enable the chosen environment.
4. Click the *Test Connection* button to verify your connection.
5. Click the *Save* button to save your settings.

![Account Settings](resources/plugin-settings.png)

## Configuring AvaTax Plugin Options

1. Visit the settings page at `Settings > Avatax Tax Adjuster`
2. *Tax Calculation Enabled* - enable or disable tax calculation independantly of other settings.
3. *Committing Enabled* - enable or disable document committing.
4. *Address Validation Enabled* - enable or disable Avalara's address verification.
5. *Debugging enabled* - while setting up and testing enable debugging to log all API interactions. Be sure to disable once live.
6. Click the *Save* button to save your settings.

![Plugin Options](resources/plugin-options.png)

## Using AvaTax Tax Adjuster

1. Visit `Commerce > Settings > Tax Categories`. A tax category with the handle "avatax" should exist, if not, create one.
2. Visit `Commerce > Settings > Product Types`. For each product type to use tax rates provided by Avalara, select the AvaTax category from the "Available Tax Categories" field.

![Product Tax Category](resources/tax-category.png)

After completing the installation and configuration, AvaTax will calculate and apply sales tax to all orders with a valid shipping address.

## Tax Codes

*E.g. 'P0000000' - Tangible personal property (TPP)*.

You can set the default [Avalara Tax Code](https://taxcode.avatax.avalara.com/) by setting the `defaultTaxCode` value in your config file at `craft/config/avataxtaxadjuster.php`. This is the default tax code that will get sent to Avalara for all products.

You can also set a specific Tax Code for each product by adding a custom field to your Products. 

To set up the override field:

1. Add a new field with a handle of `avataxTaxCode`. Note that the field "Name" can be anything you'd like, e.g. "AvaTax Tax Code" or "Product Tax Code", but the field "Handle" must match `avataxTaxCode` and is case sensitive.
2. Visit `Commerce > Settings > Product Types` and click the name of your Product Type.
2. Click the *Product Fields* tab.
3. Add the new `avataxTaxCode` field and save.

In your product entries you can now enter any text to send along as the AvaTax Tax Code. If this field does not exist or is left empty the default tax code setting in your config file will be used. 

Hint: You can set up this field to be plain text, or a dropdown with pre-configured values for your use case.

## Shipping Codes

*E.g. 'FR' - Shipping Only - common carrier - FOB destination.*

Shipping charges are sent as a separate line item to AvaTax. You can set your default [Avalara Tax Code](https://taxcode.avatax.avalara.com/) for shipping charges by setting the `defaultShippingCode` value in your config file at `craft/config/avataxtaxadjuster.php`.

## Refunds

Craft Commerce only supports refunds for completed transactions if the [payment gateway](https://craftcommerce.com/support/which-payment-gateways-do-you-support) supports refunds. If refunds are supported for an order Craft displays a "Refund" button in the order’s Transaction history. Triggering a refund in this way will issue a new Return Invoice for full amount of the corresponding AvaTax transaction.

Partial refunds, returns, or any other changes must be done manually via the AvaTax website.

## AvaTax Tax Adjuster Roadmap

Some things to do, and ideas for potential features:

* Enable/Disable tax calculation on a per-product basis.
* Config settings for default tax codes at the Product Type level.

---

Brought to you by [Rob Knecht](https://github.com/rmknecht) and [Surprise Highway](https://github.com/surprisehighway)
