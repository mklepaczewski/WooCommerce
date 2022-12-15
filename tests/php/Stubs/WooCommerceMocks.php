<?php


namespace Mollie\WooCommerceTests\Stubs;


use Mollie\WooCommerceTests\TestCase;
use PHPUnit_Framework_Exception;
use WC_Countries;

class WooCommerceMocks extends TestCase
{

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcCountries()
    {
        $item = $this->createConfiguredMock(
            WC_Countries::class,
            [
                'get_allowed_countries' => ['IT' => 'Italy'],
                'get_shipping_countries' => ['IT' => 'Italy'],
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcCustomer($country = 'IT')
    {
        $item = $this->createConfiguredMock(
            'WC_Customer',
            [
                'get_shipping_country' => $country,
                'get_billing_country' => $country

            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wooCommerce(
        $subtotal = 0,
        $shippingTotal = 0,
        $total = 0,
        $tax = 0,
        $country = 'IT'
    ) {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [
            ]
        );
        $item->wooCommerce->cart = $this->wcCart($subtotal, $shippingTotal, $total, $tax);
        $item->wooCommerce->customer = $this->wcCustomer($country);
        $item->wooCommerce->shipping = $this->wcShipping();
        $item->wooCommerce->session = $this->wcSession();

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcCart($subtotal, $shippingTotal, $total, $tax)
    {
        $item = $this->createConfiguredMock(
            'WC_Cart',
            [
                'needs_shipping' => true,
                'get_subtotal' => $subtotal,
                'is_empty' => true,
                'get_shipping_total' => $shippingTotal,
                'add_to_cart' => '88888',
                'get_total_tax' => $tax,
                'get_total' => $total,
                'calculate_shipping' => null

            ]
        );

        return $item;
    }





    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcSession()
    {
        return $this->createConfiguredMock(
            'WC_Session',
            [
                'set' => null

            ]
        );
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    public function wcShippingRate($id, $label, $cost)
    {
        $item = $this->createConfiguredMock(
            'WC_Shipping_Rate',
            [
                'get_id' => $id,
                'get_label' => $label,
                'get_cost' => $cost

            ]
        );

        return $item;
    }
}
