<?php

namespace Commerce\Adjusters;

use Craft\Commerce_LineItemModel;
use Craft\Commerce_OrderAdjustmentModel;
use Craft\Commerce_OrderModel;
use Craft\AvataxTaxAdjuster_SalesTaxService as SalesTaxService;

class AvataxTaxAdjuster implements Commerce_AdjusterInterface {

  public function adjust(Commerce_OrderModel &$order, array $lineItems = []){

    if ($order->shippingAddress !== null && $order->shippingMethod != null && sizeof($order->lineItems) > 0)
    {
      $taxService = new SalesTaxService;

      $salesTax = $taxService->createSalesOrder($order);

      $order->baseTax = $order->baseTax + $salesTax;

      $taxAdjuster = new Commerce_OrderAdjustmentModel();

      $taxAdjuster->type = "Tax";
      $taxAdjuster->name = "Sales Tax";
      $taxAdjuster->description = "Adds $".$salesTax." of tax to the order";
      $taxAdjuster->amount = +$salesTax;
      $taxAdjuster->orderId = $order->id;
      // If your Adjuster affects lineItems rather than the total, you record the ids here
      $taxAdjuster->optionsJson = [ 'lineItemsAffected' => null ];
      $taxAdjuster->included = false;

      return [$taxAdjuster];
    } else {
      return [];
    };
  }
}
