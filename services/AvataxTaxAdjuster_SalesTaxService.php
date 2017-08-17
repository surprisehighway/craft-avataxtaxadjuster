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
    public $settings = array();

    public function __construct()
    {
        $this->settings = $this->initSettings();
    }

    private function initSettings()
    {
        $plugin = craft()->plugins->getPlugin('AvataxTaxAdjuster');

        $settings = array();

        $settings['accountId'] = $plugin->getSettings()->getAttribute('accountId');
        $settings['licenseKey'] = $plugin->getSettings()->getAttribute('licenseKey');
        $settings['companyCode'] = $plugin->getSettings()->getAttribute('companyCode');

        $settings['sandboxAccountId'] = $plugin->getSettings()->getAttribute('sandboxAccountId');
        $settings['sandboxLicenseKey'] = $plugin->getSettings()->getAttribute('sandboxLicenseKey');
        $settings['sandboxCompanyCode'] = $plugin->getSettings()->getAttribute('sandboxCompanyCode');

        $settings['environment'] = $plugin->getSettings()->getAttribute('environment');

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
        $client = $this->createClient();

        $tb = new \Avalara\TransactionBuilder($client, $this->getCompanyCode(), \Avalara\DocumentType::C_SALESINVOICE, "DEFAULT");

        $tb->withCommit();

        $totalTax = $this->getTotalTax($order, $tb);

        return $totalTax;
    }


    /**
     * @return object $client
     */
    private function createClient()
    {
        $siteName = trim( craft()->getSiteName(), ';' );

        if($this->settings['environment'] == 'production')
        {
            if($this->settings['accountId'] && $this->settings['licenseKey'])
            {
                // Create a new client
                $client = new AvaTaxClient($siteName, '1.0', 'localhost', 'production');

                $client->withLicenseKey( $this->settings['accountId'], $this->settings['licenseKey'] );

                return $client;
            }
        }

        if($this->settings['environment'] == 'sandbox')
        {
            if($this->settings['sandboxAccountId'] && $this->settings['sandboxLicenseKey'])
            {
                // Create a new client
                $client = new AvaTaxClient($siteName, '1.0', 'localhost', 'sandbox');

                $client->withLicenseKey( $this->settings['sandboxAccountId'], $this->settings['sandboxLicenseKey'] );

                return $client;
            }
        }

        // Don't have credentials
        Craft::log('Avatax Account Credentials not found', LogLevel::Error, false, 'AvataxTaxAdjuster');

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

        $t = $t->create();

        if(isset($t->totalTax))
        {
            return $t->totalTax;
        }

        // Request failed
        throw new HttpException(400, 'Request could not be completed');

        Craft::log('Request to avatax.com failed', LogLevel::Error, false, 'AvataxTaxAdjuster');

        return false;
    }

}