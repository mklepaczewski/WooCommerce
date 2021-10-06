<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use WC_Order;

class DefaultRedirectStrategy implements PaymentRedirectStrategyI
{

    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order  $order
     * @param MollieOrder|MolliePayment $payment_object
     */
    public function execute($gateway, WC_Order $order, $paymentObject): string
    {
        /*
        * Redirect to payment URL
        */
        return $paymentObject->getCheckoutUrl();
    }
}
