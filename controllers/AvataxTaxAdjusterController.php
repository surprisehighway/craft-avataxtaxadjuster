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

class AvataxTaxAdjusterController extends BaseController
{
	/**
     * This allows us to use a custom route and parent
     * template for the main plugin settings.
     */
	public function actionSettings()
	{
		$plugin = craft()->plugins->getPlugin('AvataxTaxAdjuster');

		$settings = $plugin->getSettings();

		$this->renderTemplate('avataxtaxadjuster/settings/index', array(
			'settings' => $settings
		));
	}
}