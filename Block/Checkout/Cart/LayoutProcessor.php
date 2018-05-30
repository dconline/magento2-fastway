<?php
namespace Dc\Fastway\Block\Checkout\Cart;

class LayoutProcessor extends \Magento\Checkout\Block\Cart\LayoutProcessor
{
    /**
     * Show City in Shipping Estimation
     *
     * @return bool
     */
    protected function isCityActive()
    {
        return true;
    }
}
