<?php

/**
 * AvaTax Tax Adjuster plugin for Craft Commerce
 *
 * AvataxTaxAdjuster Service
 *
 * @author    Rob Knecht
 * @copyright Copyright (c) 2017 Surprise Highway
 * @link      https://github.com/surprisehighway
 * @package   AvataxTaxAdjuster
 * @since     0.0.1
 */

namespace Craft;

require CRAFT_PLUGINS_PATH.'/avataxtaxadjuster/vendor/autoload.php';
use Avalara\AvaTaxClient as AvaTaxClient;

class AvataxTaxAdjuster_SalesTaxService extends BaseApplicationComponent
{
    private $debug = false;

    public $settings = array();

    public function __construct()
    {
        $this->settings = $this->initSettings();
    }

    private function initSettings()
    {
        $plugin = craft()->plugins->getPlugin('AvataxTaxAdjuster');

        $settings = $plugin->getSettings();

        $this->debug = $settings->debug;

        return $settings;
    }

    /**
     * @return string $companyCode
     */
    private function getCompanyCode()
    {
        if($this->settings['environment'] == 'production')
        {
            $companyCode = $this->settings['companyCode'];
        }

        if($this->settings['environment'] == 'sandbox')
        {
            $companyCode = $this->settings['sandboxCompanyCode'];
        }

        return $companyCode;
    }

    /**
     * @param object Commerce_OrderModel $order
     * @return object
     *
     *  From any other plugin file, call it like this:
     *  craft()->avataxTaxAdjuster_salesTax->createSalesOrder()
     *
     * Creates a new sales order - a temporary transaction to determine the tax rate.
     * See "Sales Orders vs Sales Invoices" https://developer.avatax.com/blog/2016/11/04/estimating-tax-with-rest-v2/
     * See also: https://developer.avatax.com/avatax/use-cases/
     *
     */
    public function createSalesOrder($order)
    {
        if(!$this->settings->getAttribute('enableTaxCalculation'))
        {
            if($this->debug)
            {
                AvataxTaxAdjusterPlugin::log(__FUNCTION__.'(): Tax Calculation is disabled.', LogLevel::Info, true);
            }

            return false;
        }

        $client = $this->createClient();

        $tb = new \Avalara\TransactionBuilder($client, $this->getCompanyCode(), \Avalara\DocumentType::C_SALESORDER, "DEFAULT");

        $totalTax = $this->getTotalTax($order, $tb);

        return $totalTax;
    }


    /**
     * @param object Commerce_OrderModel $order
     * @return object
     *
     *  From any other plugin file, call it like this:
     *  craft()->avataxTaxAdjuster_salesTax->createSalesInvoice()
     *
     * Creates and commits a new sales invoice
     * See "Sales Orders vs Sales Invoices" https://developer.avatax.com/blog/2016/11/04/estimating-tax-with-rest-v2/
     * See also: https://developer.avatax.com/avatax/use-cases/
     *
     */
    public function createSalesInvoice($order)
    {
        if(!$this->settings['enableCommitting'])
        {
            if($this->debug)
            {
                AvataxTaxAdjusterPlugin::log(__FUNCTION__.'(): Document Committing is disabled.', LogLevel::Info, true);
            }

            return false;
        }

        $client = $this->createClient();

        $tb = new \Avalara\TransactionBuilder($client, $this->getCompanyCode(), \Avalara\DocumentType::C_SALESINVOICE, "DEFAULT");

        $tb->withCommit();

        $totalTax = $this->getTotalTax($order, $tb);

        return $totalTax;
    }

    /**
     * @param array $settings
     * @return object or boolean
     *
     *  From any other plugin file, call it like this:
     *  craft()->avataxTaxAdjuster_salesTax->connectionTest()
     *
     * Creates a new client with the given settings and tests the connection.
     * See https://developer.avalara.com/api-reference/avatax/rest/v2/methods/Utilities/Ping/
     *
     */
    public function connectionTest($settings)
    {
        $client = $this->createClient($settings);

        return $client->ping();
    }

    /**
     * @return object $client
     */
    private function createClient($settings = null)
    {
        $settings = ($settings) ? $settings : $this->settings;

        $siteName = trim( craft()->getSiteName(), ';' );

        if($settings['environment'] == 'production')
        {
            if($settings['accountId'] && $settings['licenseKey'])
            {
                // Create a new client
                $client = new AvaTaxClient($siteName, '1.0', 'localhost', 'production');

                $client->withLicenseKey( $settings['accountId'], $settings['licenseKey'] );

                return $client;
            }
        }

        if($settings['environment'] == 'sandbox')
        {
            if($settings['sandboxAccountId'] && $settings['sandboxLicenseKey'])
            {
                // Create a new client
                $client = new AvaTaxClient($siteName, '1.0', 'localhost', 'sandbox');

                $client->withLicenseKey( $settings['sandboxAccountId'], $settings['sandboxLicenseKey'] );

                return $client;
            }
        }

        // Don't have credentials
        AvataxTaxAdjusterPlugin::log('Avatax Account Credentials not found', LogLevel::Error, true);

        // throw a craft exception which displays the error cleanly
        throw new HttpException(500, 'Avatax Account Credentials not found');

        return false;
    }


    /**
     * @param object Commerce_OrderModel $order
     * @param object Avatax_TransactionBuilder $transaction
     * @return object
     *
     */
    private function getTotalTax($order, $transaction)
    {

        $shipFrom = craft()->config->get('shipFrom', 'avataxtaxadjuster');


        $t = $transaction->withAddress(
                'shipFrom',
                $shipFrom['street1'],
                $shipFrom['street2'],
                $shipFrom['street3'],
                $shipFrom['city'],
                $shipFrom['state'],
                $shipFrom['zipCode'],
                $shipFrom['country']
            )
            ->withAddress(
                'shipTo',
                $order->shippingAddress->address1,
                NULL,
                NULL,
                $order->shippingAddress->city,
                $order->shippingAddress->getState() ? $order->shippingAddress->getState() ? $order->shippingAddress->getState()->abbreviation : $order->shippingAddress->getStateText() : '',
                $order->shippingAddress->zipCode,
                $order->shippingAddress->getCountry()->iso
            );

        // Add each line item to the transaction
        foreach ($order->lineItems as $lineItem) {
            // Our product has the avatax tax category specified
            if($lineItem->taxCategory == 'Avatax'){

                $taxCode = 'P0000000';

                if(isset($lineItem->purchasable->product->avataxTaxCode)) {
                    $taxCode = $lineItem->purchasable->product->avataxTaxCode ? $lineItem->purchasable->product->avataxTaxCode : 'P0000000';
                }

               // amount, $quantity, $itemCode, $taxCode)
               $t = $t->withLine(
                    $lineItem->subtotal,    // Total amount for the line item
                    $lineItem->qty,         // Quantity
                    $lineItem->id,          // Item Code
                    $taxCode                // Tax Code - System or Custom Tax Code. Default (P0000000) is assumed.
                );
           }
        }

        // Add shipping cost as line-item
        $t = $t->withLine(
            $order->totalShippingCost,  // total amount for the line item
            1,                          // quantity
            "FR",                       // Item Code
            "FR"                        // Tax code for freight - Shipping only, common carrier - FOB destination
        );

        if($this->debug)
        {
            $model = $t; // save the model for debug logging
        }

        $t = $t->create();

        if($this->debug)
        {
            AvataxTaxAdjusterPlugin::log('\Avalara\TransactionBuilder->create(): [request] '.json_encode((array)$model).' [response] '.json_encode($t), LogLevel::Trace, true);
        }

        if(isset($t->totalTax))
        {
            return $t->totalTax;
        }

        AvataxTaxAdjusterPlugin::log('Request to avatax.com failed', LogLevel::Error, true);

        // Request failed
        throw new HttpException(400, 'Request could not be completed');

        return false;
    }

}