<?php

/**
 * AvaTax Tax Adjuster plugin for Craft Commerce
 *
 * AvataxTaxAdjuster Utilities Controller
 *
 * @author    Rob Knecht
 * @author    Mike Kroll
 * @copyright Copyright (c) 2017 Surprise Highway
 * @link      https://github.com/surprisehighway
 * @package   AvataxTaxAdjuster
 * @since     0.0.1
 */

namespace Craft;

use Craft\AvataxTaxAdjuster_SalesTaxService as SalesTaxService;

class AvataxTaxAdjuster_UtilitiesController extends BaseController
{
	public function actionConnectionTest()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();

		$taxService = new SalesTaxService;

		$settings = craft()->request->getPost('settings');
		
		$response = $taxService->connectionTest($settings);

		$this->returnJson($response);
	}
}