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
use Commerce\Helpers\CommerceCurrencyHelper;

class AvataxTaxAdjuster_SalesTaxService extends BaseApplicationComponent
{
    private $debug = false;

    private $type = null;

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
     * @return string $customerCode
     */
    private function getCustomerCode($order)
    {
        return (!empty($order->email)) ? $order->email : 'GUEST';
    }

    /**
     * @return string $transactionCode
     *
     * Use the prefixed order number as the document code so that
     * we can reference it again for subsequent calls if needed.
     */
    private function getTransactionCode($order)
    {
        $prefix = 'cr_';

        return $prefix.$order->number;
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

        $this->type = 'order';

        $client = $this->createClient();

        $tb = new \Avalara\TransactionBuilder($client, $this->getCompanyCode(), \Avalara\DocumentType::C_SALESORDER, $this->getCustomerCode($order));

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

        $this->type = 'invoice';

        $client = $this->createClient();

        $tb = new \Avalara\TransactionBuilder($client, $this->getCompanyCode(), \Avalara\DocumentType::C_SALESINVOICE, $this->getCustomerCode($order));

        $tb->withCommit();

        $totalTax = $this->getTotalTax($order, $tb);

        return $totalTax;
    }

    /**
     * @param object Commerce_OrderModel $order
     * @return object
     *
     * Refund a committed sales invoice
     * See "Refund Transaction" https://developer.avalara.com/api-reference/avatax/rest/v2/methods/Transactions/RefundTransaction
     *
     */
    public function refundTransaction($order)
    {
        $client = $this->createClient();

        $request = array(
            'companyCode' => $this->getCompanyCode(),
            'transactionCode' => $this->getTransactionCode($order)
        );

        $model = array(
            'refundTransactionCode' => $request['transactionCode'].'.1',
            'refundType' => \Avalara\RefundType::C_FULL,
            'refundDate' => date('Y-m-d'),
            'referenceCode' => 'Refund from Craft Commerce'
        );

        extract($request);

        $response = $client->refundTransaction($companyCode, $transactionCode, null, null, null, $model);

        if($this->debug)
        {
            $request = array_merge($request, $model);

            AvataxTaxAdjusterPlugin::log('\Avalara\Client->refundTransaction(): [request] '.json_encode($request).' [response] '.json_encode($response), LogLevel::Trace, true);
        }

        if(is_array($response) && isset($response->status) && $response->status == 'Committed')
        {
            AvataxTaxAdjusterPlugin::log('Transaction Code '.$transactionCode.' was successfully refunded.', LogLevel::Info, true);

            return true;
        }

        AvataxTaxAdjusterPlugin::log('Transaction Code '.$transactionCode.' could not be refunded.', LogLevel::Error, true);

        return false;
    }

    /**
     * @param object Commerce_AddressModel $address
     * @return object
     *
     *  From any other plugin file, call it like this:
     *  craft()->avataxTaxAdjuster_salesTax->validateAddress()
     *
     * Validates and address
     * See: https://developer.avalara.com/api-reference/avatax/rest/v2/methods/Addresses/ResolveAddressPost/
     *
     */
    public function validateAddress($address)
    {
        if(!$this->settings['enableAddressValidation'])
        {
            if($this->debug)
            {
                AvataxTaxAdjusterPlugin::log(__FUNCTION__.'(): Address validation is disabled.', LogLevel::Info, true);
            }

            return false;
        }

        $signature = $this->getAddressSignature($address);
        $cacheKey = 'avatax-address-'.$signature;

        // Check if validated address has been cached, if not make api call.
        $response = craft()->cache->get($cacheKey);
        //if($response) AvataxTaxAdjusterPlugin::log('Cached address found: '.$cacheKey, LogLevel::Trace, true); 

        if(!$response) 
        {
            $request = array(
                'line1' => $address->address1,
                'line2' => $address->address2,
                'line3' => '', 
                'city' => $address->city,
                'region' => $address->getState() ? $address->getState() ? $address->getState()->abbreviation : $address->getStateText() : '', 
                'postalCode' => $address->zipCode,
                'country' => $address->country->iso, 
                'textCase' => 'Mixed',
                'latitude' => '',
                'longitude' => ''
            );

            extract($request);

            $client = $this->createClient();

            $response = $client->resolveAddress($line1, $line2, $line3, $city, $region, $postalCode, $country, $textCase, $latitude, $longitude);

            craft()->cache->set($cacheKey, $response);

            if($this->debug)
            {
                AvataxTaxAdjusterPlugin::log('\Avalara\AvaTaxClient->resolveAddress(): [request] '.json_encode($request).' [response] '.json_encode($response), LogLevel::Trace, true);
            }
        }

        if(isset($response->validatedAddresses) || isset($response->coordinates))
        {
            return true;
        }

        AvataxTaxAdjusterPlugin::log('Address validation failed.', LogLevel::Error, true);

        // Request failed
        throw new HttpException(400, 'Invalid address.');

        return false;
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

        $pluginName = 'Craft Commerce '.AvataxTaxAdjusterPlugin::getName();
        $pluginVersion = AvataxTaxAdjusterPlugin::getVersion();
        $machineName = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'localhost';

        if($settings['environment'] == 'production')
        {
            if($settings['accountId'] && $settings['licenseKey'])
            {
                // Create a new client
                $client = new AvaTaxClient($pluginName, $pluginVersion, $machineName, 'production');

                $client->withLicenseKey( $settings['accountId'], $settings['licenseKey'] );

                return $client;
            }
        }

        if($settings['environment'] == 'sandbox')
        {
            if($settings['sandboxAccountId'] && $settings['sandboxLicenseKey'])
            {
                // Create a new client
                $client = new AvaTaxClient($pluginName, $pluginVersion, $machineName, 'sandbox');

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
        if($this->settings['enableAddressValidation'])
        {
            // Make sure we have a valid address before continuing.
            $this->validateAddress($order->shippingAddress);
        }

        $defaultTaxCode = $this->settings['defaultTaxCode'];
        $defaultShippingCode = $this->settings['defaultShippingCode'];

        $t = $transaction->withTransactionCode(
                $this->getTransactionCode($order)
            )
            ->withAddress(
                'shipFrom',
                $this->settings['shipFromStreet1'],
                $this->settings['shipFromStreet2'],
                $this->settings['shipFromStreet3'],
                $this->settings['shipFromCity'],
                $this->settings['shipFromState'],
                $this->settings['shipFromZipCode'],
                $this->settings['shipFromCountry']
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

                $taxCode = $defaultTaxCode ? $defaultTaxCode : 'P0000000';

                if(isset($lineItem->purchasable->product->avataxTaxCode)) {
                    $taxCode = $lineItem->purchasable->product->avataxTaxCode ? $lineItem->purchasable->product->avataxTaxCode : $defaultTaxCode;
                }

                $itemCode = $lineItem->id;

                if(!empty($lineItem->sku)) {
                    $itemCode = $lineItem->sku;
                }

               // amount, $quantity, $itemCode, $taxCode)
               $t = $t->withLine(
                    $lineItem->subtotal,    // Total amount for the line item
                    $lineItem->qty,         // Quantity
                    $itemCode,              // Item Code
                    $taxCode                // Tax Code - Default or Custom Tax Code.
                );

               // add human-readable description to line item
               $t = $t->withLineDescription($lineItem->purchasable->product->title);
           }
        }

        // Add each discount line item
        foreach ($order->adjustments as $adjustment) {
            if($adjustment->type == 'Discount') {
                $t = $t->withLine(
                    $adjustment->amount, // Total amount for the line item
                    1,                   // quantity
                    $adjustment->name,   // Item Code
                    "OD010000"           // Tax Code - default to OD010000 - Discounts/retailer coupons associated w/taxable items only
                );

                // add description to discount line item
                $t = $t->withLineDescription($adjustment->description);
            }
        }

        // Add shipping cost as line-item
        $shippingTaxCode = $defaultShippingCode ? $defaultShippingCode : 'FR';

        $t = $t->withLine(
            $order->totalShippingCost,  // total amount for the line item
            1,                          // quantity
            "FR",                       // Item Code
            $shippingTaxCode            // Tax code for freight (Shipping)
        );

        // add description to shipping line item
        $t = $t->withLineDescription('Total Shipping Cost');

        // add entity/use code if set for a logged-in User
        if(!is_null($order->customer->user))
        {
            if(isset($order->customer->user->avataxCustomerUsageType) 
            && !empty($order->customer->user->avataxCustomerUsageType->value))
            {
                $t = $t->withEntityUseCode($order->customer->user->avataxCustomerUsageType->value);
            }
        }

        if($this->debug)
        {
            // workaround to save the model as array for debug logging
            $m = $t; $model = $m->createAdjustmentRequest(null, null)['newTransaction'];
        }

        $signature = $this->getOrderSignature($order);
        $cacheKey = 'avatax-'.$this->type.'-'.$signature;

        // Check if tax request has been cached when not committing, if not make api call.
        $response = craft()->cache->get($cacheKey);
        //if($response) AvataxTaxAdjusterPlugin::log('Cached order found: '.$cacheKey, LogLevel::Trace, true); 

        if(!$response || $this->type === 'invoice') 
        {
            $response = $t->create();

            craft()->cache->set($cacheKey, $response);

            if($this->debug)
            {
                AvataxTaxAdjusterPlugin::log('\Avalara\TransactionBuilder->create() '.$this->type.': [request] '.json_encode($model).' [response] '.json_encode($response), LogLevel::Trace, true);
            }
        }

        if(isset($response->totalTax))
        {
            return $response->totalTax;
        }

        AvataxTaxAdjusterPlugin::log('Request to avatax.com failed', LogLevel::Error, true);

        // Request failed
        throw new HttpException(400, 'Request could not be completed');

        return false;
    }


    /**
     * Returns a hash derived from the order's properties.
     */
    private function getOrderSignature(Commerce_OrderModel $order)
    {
        $orderNumber = $order->number;
        $shipping = $order->totalShippingCost;
        $discount = $order->totalDiscount;
        $tax = $order->totalTax;
        $total = CommerceCurrencyHelper::round($order->totalPrice);

        $address1 = $order->shippingAddress->address1;
        $address2 = $order->shippingAddress->address2;
        $city = $order->shippingAddress->city;
        $zipCode = $order->shippingAddress->zipCode;
        $country = $order->shippingAddress->country->iso;
        $address = $address1.$address2.$city.$zipCode.$country;

        $lineItems = '';
        foreach ($order->lineItems as $lineItem)
        {
            $itemCode = $lineItem->id;
            $subtotal = $lineItem->subtotal;
            $qty = $lineItem->qty;
            $lineItems .= $itemCode.$subtotal.$qty;
        }

        return md5($orderNumber.$shipping.$discount.$tax.$total.$lineItems.$address); 
    }

    /**
     * Returns a hash derived from the address.
     */
    private function getAddressSignature(Commerce_AddressModel $address)
    {
        $address1 = $address->address1;
        $address2 = $address->address2;
        $city = $address->city;
        $zipCode = $address->zipCode;
        $country = $address->country->iso;

        return md5($address1.$address2.$city.$zipCode.$country); 
    }

}
