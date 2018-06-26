<?php
/**
 * AvaTax Tax Adjuster plugin for Craft Commerce
 *
 * Calculate tax using Avalara&#39;s Avatax
 *
 *
 * @author    Rob Knecht
 * @author    Mike Kroll
 * @copyright Copyright (c) 2017 Surprise Highway
 * @link      https://github.com/surprisehighway
 * @package   AvataxTaxAdjuster
 * @since     0.0.1
 */

namespace Craft;

require('adjusters/AvataxTaxAdjuster.php');

class AvataxTaxAdjusterPlugin extends BasePlugin
{
    /**
     * Called after the plugin class is instantiated; do any one-time initialization here such as hooks and events:
     *
     * craft()->on('entries.saveEntry', function(Event $event) {
     *    // ...
     * });
     *
     * or loading any third party Composer packages via:
     *
     * require_once __DIR__ . '/vendor/autoload.php';
     *
     * @return mixed
     */
    public function init()
    {
        parent::init();

        require __DIR__.'/vendor/autoload.php';

        craft()->on('commerce_orders.onBeforeOrderComplete', [$this, 'onBeforeOrderComplete']);
        craft()->on('commerce_addresses.onBeforeSaveAddress', [$this, 'onBeforeSaveAddress']);
        craft()->on('commerce_payments.onRefundTransaction', [$this, 'onRefundTransaction']);

    }

    /**
     * Raised before a cart is completed and becomes an order.
     * Create a sales invoice in avatax.
     */
    public function onBeforeOrderComplete(Event $event)
    {
        /** @var Commerce_OrderModel $order */
        $order = $event->params['order'];

        craft()->avataxTaxAdjuster_salesTax->createSalesInvoice($order);
    }

    /**
     * Raised before address has been saved.
     * Validate an address in avatax.
     */
    public function onBeforeSaveAddress(Event $event)
    {
        /** @var Commerce_AddressModel $address */
        $address = $event->params['address'];

        craft()->avataxTaxAdjuster_salesTax->validateAddress($address);
    }

    /**
     * Raised after a transaction was attempted to be refunded.
     * Void a transaction in.
     */
    public function onRefundTransaction(Event $event)
    {
        /** @var Commerce_TransactionModel $transaction */
        $transaction = $event->params['transaction'];

        if($transaction->status == 'success')
        {
            craft()->avataxTaxAdjuster_salesTax->refundTransaction($transaction->order);
        }
    }

    /**
     * Returns the user-facing name.
     *
     * @return mixed
     */
    public function getName()
    {
         return Craft::t('AvaTax Tax Adjuster');
    }

    /**
     * Plugins can have descriptions of themselves displayed on the Plugins page by adding a getDescription() method
     * on the primary plugin class:
     *
     * @return mixed
     */
    public function getDescription()
    {
        return Craft::t('Calculates tax rates with Avalara AvaTax');
    }

    /**
     * Plugins can have links to their documentation on the Plugins page by adding a getDocumentationUrl() method on
     * the primary plugin class:
     *
     * @return string
     */
    public function getDocumentationUrl()
    {
        return 'https://github.com/surprisehighway/craft-avataxtaxadjuster/blob/master/README.md';
    }

    /**
     * Plugins can now take part in Craft’s update notifications, and display release notes on the Updates page, by
     * providing a JSON feed that describes new releases, and adding a getReleaseFeedUrl() method on the primary
     * plugin class.
     *
     * @return string
     */
    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/surprisehighway/craft-avataxtaxadjuster/master/releases.json';
    }

    /**
     * Returns the version number.
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.0.6';
    }

    /**
     * As of Craft 2.5, Craft no longer takes the whole site down every time a plugin’s version number changes, in
     * case there are any new migrations that need to be run. Instead plugins must explicitly tell Craft that they
     * have new migrations by returning a new (higher) schema version number with a getSchemaVersion() method on
     * their primary plugin class:
     *
     * @return string
     */
    public function getSchemaVersion()
    {
        return '1.0.0';
    }

    /**
     * Returns the developer’s name.
     *
     * @return string
     */
    public function getDeveloper()
    {
        return 'Surprise Highway';
    }

    /**
     * Returns the developer’s website URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'https://github.com/surprisehighway';
    }

    /**
     * Returns whether the plugin should get its own tab in the CP header.
     *
     * @return bool
     */
    public function hasCpSection()
    {
        return false;
    }

    /**
     * Called right before your plugin’s row gets stored in the plugins database table, and tables have been created
     * for it based on its records.
     */
    public function onBeforeInstall()
    {
    }

    /**
     * Called right after your plugin’s row has been stored in the plugins database table, and tables have been
     * created for it based on its records.
     */
    public function onAfterInstall()
    {
        // Create an "avatax" tax category
        $avataxTaxCategory = craft()->commerce_taxCategories->getTaxCategoryByHandle('avatax');

        if(!$avataxTaxCategory)
        {
            $newTaxCategoryModel = Commerce_TaxCategoryModel::populateModel( array(
                'name' => 'Avatax',
                'handle' => 'avatax',
                'description' => 'Calculate tax rates using Avatax Avatax',
                'default' => FALSE,
            ) );

            /** @param Commerce_TaxCategoryModel $model **/
            if ( craft()->commerce_taxCategories->saveTaxCategory($newTaxCategoryModel) )
            {
                Craft::log('Avatax tax category created successfully.');
            }
            else
            {
                Craft::log('Could not save the Avatax tax category.', LogLevel::Warning);
            }

        }

        // Create an "avatax field group"
        $avataxFieldGroupModel = new FieldGroupModel();
        $avataxFieldGroupModel->name = 'Avatax';

        if( craft()->fields->saveGroup($avataxFieldGroupModel) )
        {
            Craft::log('Avatax field group created successfully.', LogLevel::Info);

            // Create avataxTaxCode field
            $avataxTaxCodeModel = new FieldModel();
            $avataxTaxCodeModel->groupId      = $avataxFieldGroupModel->id;
            $avataxTaxCodeModel->name         = 'AvaTax Tax Code';
            $avataxTaxCodeModel->handle       = 'avataxTaxCode';
            $avataxTaxCodeModel->translatable = true;
            $avataxTaxCodeModel->type         = 'PlainText';
            $avataxTaxCodeModel->instructions = 'Specify an [Avalara Tax Code](https://taxcode.avatax.avalara.com) to use for this product.';
            $avataxTaxCodeModel->settings = array(
                'placeholder' => '',
                'multiline' => '',
                'initialRows' => '4',
                'maxLength' => ''
            );

            if (craft()->fields->saveField($avataxTaxCodeModel))
            {
                Craft::log('Avatax Tax Code field created successfully.');
            }
            else
            {
                Craft::log('Could not save the Avatax Tax Code field.', LogLevel::Warning);
            }

            // Create avataxCustomerUsageType field
            $avataxCustomerUsageTypeModel = new FieldModel();
            $avataxCustomerUsageTypeModel->groupId      = $avataxFieldGroupModel->id;
            $avataxCustomerUsageTypeModel->name         = 'AvaTax Customer Usage Type';
            $avataxCustomerUsageTypeModel->handle       = 'avataxCustomerUsageType';
            $avataxCustomerUsageTypeModel->translatable = true;
            $avataxCustomerUsageTypeModel->type         = 'Dropdown';
            $avataxCustomerUsageTypeModel->instructions = 'Select an [Entity/Use Code](https://help.avalara.com/000_Avalara_AvaTax/Exemption_Reason_Matrices_for_US_and_Canada) to exempt this customer from tax.';
            $avataxCustomerUsageTypeModel->settings = array(
                'options' => array(
                    array('label' => '', 'value' => '', 'default' => ''),
                    array('label' => 'A', 'value' => 'A. Federal government (United States)', 'default' => ''),
                    array('label' => 'B', 'value' => 'B. State government (United States)', 'default' => ''),
                    array('label' => 'C', 'value' => 'C. Tribe / Status Indian / Indian Band (both)', 'default' => ''),
                    array('label' => 'D', 'value' => 'D. Foreign diplomat (both)', 'default' => ''),
                    array('label' => 'E', 'value' => 'E. Charitable or benevolent org (both)', 'default' => ''),
                    array('label' => 'F', 'value' => 'F. Religious or educational org (both)', 'default' => ''),
                    array('label' => 'G', 'value' => 'G. Resale (both)', 'default' => ''),
                    array('label' => 'H', 'value' => 'H. Commercial agricultural production (both)', 'default' => ''),
                    array('label' => 'I', 'value' => 'I. Industrial production / manufacturer (both)', 'default' => ''),
                    array('label' => 'J', 'value' => 'J. Direct pay permit (United States)', 'default' => ''),
                    array('label' => 'K', 'value' => 'K. Direct mail (United States)', 'default' => ''),
                    array('label' => 'L', 'value' => 'L. Other (both)', 'default' => ''),
                    array('label' => 'M', 'value' => 'M. Not Used', 'default' => ''),
                    array('label' => 'N', 'value' => 'N. Local government (United States)', 'default' => ''),
                    array('label' => 'O', 'value' => 'O. Not Used', 'default' => ''),
                    array('label' => 'P', 'value' => 'P. Commercial aquaculture (Canada)', 'default' => ''),
                    array('label' => 'Q', 'value' => 'Q. Commercial Fishery (Canada)', 'default' => ''),
                    array('label' => 'R', 'value' => 'R. Non-resident (Canada)', 'default' => '')
                )
            );

            if (craft()->fields->saveField($avataxCustomerUsageTypeModel))
            {
                Craft::log('Avatax Customer Usage Type field created successfully.');
            }
            else
            {
                Craft::log('Could not save the Avatax Customer Usage Type field.', LogLevel::Warning);
            }
        }
        else
        {
            Craft::log('Could not save the Avatax field group. ', LogLevel::Warning);
        }

        craft()->request->redirect('/admin/avataxtaxadjuster/settings');
    }

    /**
     * Called right before your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onBeforeUninstall()
    {
        // Note: We can't remove the tax category as some prducts may only have the Avatax Category selected.

        // Remove the avatax tax category
        // $avataxTaxCategory = craft()->commerce_taxCategories->getTaxCategoryByHandle('avatax');

        // if($avataxTaxCategory)
        // {
        //     /** @param Commerce_TaxCategoryModel $model **/
        //     if ( craft()->commerce_taxCategories->deleteTaxCategoryById($avataxTaxCategory->id) )
        //     {
        //         Craft::log('Avatax tax category deleted successfully.');
        //     }
        //     else
        //     {
        //         Craft::log('Could not delete the Avatax tax category.', LogLevel::Warning);
        //     }
        // }
    }

    /**
     * Called right after your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onAfterUninstall()
    {
    }

    /**
     * Defines the attributes that model your plugin’s available settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        return array(
            'environment' => array( AttributeType::String, 'label' => 'Environment', 'default' => 'sandbox', 'required' => true),
            'accountId'   => array( AttributeType::String, 'label' => 'Account ID', 'default' => '', 'required' => true),
            'licenseKey'  => array( AttributeType::String, 'label' => 'License Key', 'default' => '', 'required' => true),
            'companyCode' => array( AttributeType::String, 'label' => 'Company Code', 'default' => '', 'required' => false),
            'sandboxAccountId'   => array( AttributeType::String, 'label' => 'Account ID', 'default' => '', 'required' => false),
            'sandboxLicenseKey'  => array( AttributeType::String, 'label' => 'License Key', 'default' => '', 'required' => false),
            'sandboxCompanyCode' => array( AttributeType::String, 'label' => 'Company Code', 'default' => '', 'required' => false),
            'shipFromName'    => array( AttributeType::String, 'label' => 'Name', 'default' => '', 'required' => true),
            'shipFromStreet1' => array( AttributeType::String, 'label' => 'Street 1', 'default' => '', 'required' => true),
            'shipFromStreet2' => array( AttributeType::String, 'label' => 'Street 1', 'default' => '', 'required' => false),
            'shipFromStreet3' => array( AttributeType::String, 'label' => 'Street 3', 'default' => '', 'required' => false),
            'shipFromCity'    => array( AttributeType::String, 'label' => 'City', 'default' => '', 'required' => true),
            'shipFromState'   => array( AttributeType::String, 'label' => 'State/Province', 'default' => '', 'required' => true),
            'shipFromZipCode' => array( AttributeType::String, 'label' => 'Postal Code', 'default' => '', 'required' => true),
            'shipFromCountry' => array( AttributeType::String, 'label' => 'Country', 'default' => '', 'required' => true),
            'enableTaxCalculation'    => array( AttributeType::Bool, 'label' => 'Enable Tax Calculation', 'default' => true, 'required' => false),
            'enableCommitting'        => array( AttributeType::Bool, 'label' => 'Enable Document Committing', 'default' => true, 'required' => false),
            'enableAddressValidation' => array( AttributeType::Bool, 'label' => 'Enable Address Validation', 'default' => true, 'required' => false),
            'defaultTaxCode'          => array( AttributeType::String, 'label' => 'Default Tax Code', 'default' => 'P0000000', 'required' => true),
            'defaultShippingCode'     => array( AttributeType::String, 'label' => 'Default Shipping Code', 'default' => 'FR', 'required' => true),
            'debug'                   => array( AttributeType::Bool, 'label' => 'Debug', 'default' => false, 'required' => false),
        );
    }

    /**
     * Returns a URL to your plugin’s settings.
     *
     * @return string
     */
    public function getSettingsUrl()
    {
        return 'avataxtaxadjuster/settings';
    }

    /**
     * If you need to do any processing on your settings’ post data before they’re saved to the database, you can
     * do it with the prepSettings() method:
     *
     * @param mixed $settings  The Widget's settings
     *
     * @return mixed
     */
    public function prepSettings($settings)
    {
        // Modify $settings here...

        return $settings;
    }

    /**
     * @param array|BaseModel $values
     */
    public function setSettings($values)
    {
        if (!$values)
        {
            $values = array();
        }

        if (is_array($values))
        {
            // Merge in any values that are stored in craft/config/avataxtaxadjuster.php
            foreach ($this->getSettings() as $key => $value)
            {
                if(substr($key, 0, 8) === 'shipFrom')
                {
                    $shipFrom = craft()->config->get('shipFrom', 'avataxtaxadjuster');
                    $shipFromKey = lcfirst(str_replace('shipFrom', '', $key));
                    if(!empty($shipFrom[ $shipFromKey]))
                    {
                        $values[$key] = $shipFrom[$shipFromKey];
                    }
                }
                else
                {
                    $configValue = craft()->config->get($key, 'avataxtaxadjuster');
                    if ($configValue !== null)
                    {
                        $values[$key] = $configValue;
                    }
                }
            }
        }

        parent::setSettings($values);
    }

    /**
     * Define custom CP routes.
     *
     * @return array
     */
    public function registerCpRoutes()
    {
        return array(
            'avataxtaxadjuster/settings' => array('action' => 'avataxTaxAdjuster/settings'),
            'avataxtaxadjuster/logs' => array('action' => 'avataxTaxAdjuster_Utilities/logs')
        );
    }

    public function commerce_registerOrderAdjusters(){

        return [
            new \Commerce\Adjusters\AvataxTaxAdjuster
        ];
    }

}