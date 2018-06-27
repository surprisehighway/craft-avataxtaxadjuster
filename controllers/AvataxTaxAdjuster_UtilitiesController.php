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
	private $currentLogFileName = 'avataxtaxadjuster.log';

	/**
     * Handle the connection test xhr request
     */
	public function actionConnectionTest()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();

		$taxService = new SalesTaxService;

		$settings = craft()->request->getPost('settings');
		
		$response = $taxService->connectionTest($settings);

		$this->returnJson($response);
	}

	/**
     * Display recent plugin logging
     *
     * Write to the plugin log by using the plugin log wrapper method:
     * `AvataxTaxAdjusterPlugin::log('Message: [response] '.json_encode($response), LogLevel::Trace, true);`
     *
     * This is just a stripped-down copy of Craft's own log file display controller.
     * @see craft/app/controllers/UtilsController.php
     */
	public function actionLogs()
	{
		craft()->config->maxPowerCaptain();

		if (IOHelper::folderExists(craft()->path->getLogPath()))
		{
			$dateTimePattern = '/^[0-9]{4}\/[0-9]{2}\/[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}/';

			$logEntries = array();

			$currentLogFileName = $this->currentLogFileName;
			$currentFullPath = craft()->path->getLogPath().$currentLogFileName;

			if (IOHelper::fileExists($currentFullPath))
			{
				// Split the log file's contents up into arrays of individual logs, where each item is an array of
				// the lines of that log.
				$contents = IOHelper::getFileContents(craft()->path->getLogPath().$currentLogFileName);

				$requests = explode('******************************************************************************************************', $contents);

				foreach ($requests as $request)
				{
					$logChunks = preg_split('/^(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}) \[(.*?)\] \[(.*?)\] /m', $request, null, PREG_SPLIT_DELIM_CAPTURE);

					// Ignore the first chunk
					array_shift($logChunks);

					// Loop through them
					$totalChunks = count($logChunks);
					for ($i = 0; $i < $totalChunks; $i += 4)
					{
						$logEntryModel = new LogEntryModel();

						$logEntryModel->dateTime = DateTime::createFromFormat('Y/m/d H:i:s', $logChunks[$i]);
						$logEntryModel->level = $logChunks[$i+1];
						$logEntryModel->category = $logChunks[$i+2];

						$message = $logChunks[$i+3];
						$rowContents = explode("\n", $message);

						// This is a non-devMode log entry.
						$logEntryModel->message = str_replace('[Forced] ', '', $rowContents[0]);

						// Convert model to array
						$logEntryArray = ArrayHelper::flattenArray($logEntryModel);

						// Check for custom markers
						$response = explode('[response] ', $logEntryArray['message']);

						if(!empty($response[1]))
						{
							$logEntryArray['response'] = json_decode($response[1]);
						 	$logEntryArray['message'] = $response[0];
						}

						$request = explode('[request] ', $logEntryArray['message']);

						if(!empty($request[1]))
						{
							$logEntryArray['request'] = json_decode($request[1]);
						 	$logEntryArray['message'] = $request[0];
						}

						// And save the log entry.
						array_unshift($logEntries, $logEntryArray);
					}
				}
			}
		}

		$this->renderTemplate('avataxtaxadjuster/settings/logs', array(
			'logEntries' => $logEntries
		));
	}

	/**
     * Delete all log files
     */
	public function actionClearLogs()
	{
		$currentLogFileName = $this->currentLogFileName;
		$currentFullPath = craft()->path->getLogPath().$currentLogFileName;

        IOHelper::deleteFile($currentFullPath, true);

        craft()->request->redirect(craft()->request->urlReferrer);
	}
}