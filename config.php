<?php

/**
 * Copy and place within your craft config directory at /craft/config/avataxtaxadjuster.php
 * Be sure to rename the config file 'avataxtaxadjuster.php'
 */
return [
	// The address you will be posting from.
	'shipFrom' => [
		"name"    => "John Doe",
		"street1" => "201 E Randolph St",
		"street2" => "",
		"street3" => "",
		"city"    => "Chicago",
		"state"   => "IL",
		"zipCode" => "60601",
		"country" => "US",
	],
	// The default Avalara Tax Code to use for products.
	'defaultTaxCode' => 'P0000000',
	// The default Avalara Tax Code to use for shipping.
	'defaultShippingCode' => 'FR'
];