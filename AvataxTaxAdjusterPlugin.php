<?php
/**
 * AvaTax Tax Adjuster plugin for Craft Commerce
 *
 * Calculate tax using Avalara&#39;s Avatax
 *
 *
 * @author    Rob Knecht
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
        return '1.0.2';
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

        craft()->request->redirect('/admin/settings/plugins/avataxtaxadjuster');
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
            'accountId' => array( AttributeType::String, 'label' => 'Account ID', 'default' => '', 'required' => true),
            'licenseKey' => array( AttributeType::String, 'label' => 'License Key', 'default' => '', 'required' => true),
            'companyCode' => array( AttributeType::String, 'label' => 'Company Code', 'default' => '', 'required' => false),
            'sandboxAccountId' => array( AttributeType::String, 'label' => 'Account ID', 'default' => '', 'required' => false),
            'sandboxLicenseKey' => array( AttributeType::String, 'label' => 'License Key', 'default' => '', 'required' => false),
            'sandboxCompanyCode' => array( AttributeType::String, 'label' => 'Company Code', 'default' => '', 'required' => false),

        );
    }

    /**
     * Returns the HTML that displays your plugin’s settings.
     *
     * @return mixed
     */
    public function getSettingsHtml()
    {
       return craft()->templates->render('avataxtaxadjuster/AvataxTaxAdjuster_Settings', array(
           'settings' => $this->getSettings()
       ));
    }


    /**
     * Returns the HTML that displays your plugin’s settings.
     *
     * @return string
     */
    public function getSettingsUrl()
    {
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

    public function commerce_registerOrderAdjusters(){

        return [
            new \Commerce\Adjusters\AvataxTaxAdjuster
        ];
    }

}