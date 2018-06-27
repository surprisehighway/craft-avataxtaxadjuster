<?php

/**
 * To override plugin settings copy this template and place it within 
 * your craft config directory at /craft/config/avataxtaxadjuster.php
 * Be sure to rename the config file 'avataxtaxadjuster.php'
 *
 * These are all available overrides, remove the ones you don't want.
 */
return [
	// The address you will be posting from.
	'shipFrom' => [
		'name'    => 'John Doe',
		'street1' => '201 E Randolph St',
		'street2' => '',
		'street3' => '',
		'city'    => 'Chicago',
		'state'   => 'IL',
		'zipCode' => '60601',
		'country' => 'US',
	],
	// The default Avalara Tax Code to use for products.
	'defaultTaxCode' => 'P0000000',
	// The default Avalara Tax Code to use for shipping.
	'defaultShippingCode' => 'FR',
	// Production account information.
    'accountId'          => '',
    'licenseKey'         => '',
    'companyCode'        => '',
    // Sandbox account information.
    'sandboxAccountId'   => '',
    'sandboxLicenseKey'  => '',
    'sandboxCompanyCode' => '',
    // Environment - 'production' or 'sandbox'.
    'environment' => 'sandbox',
    // AvaTax options - true or false
    'enableTaxCalculation'    => true,
    'enableCommitting'        => true,
    'enableAddressValidation' => true,
    // Enable debugging - true or false
    'debug'                   => true,
];