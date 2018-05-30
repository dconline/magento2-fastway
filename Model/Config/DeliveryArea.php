<?php
namespace Dc\Fastway\Model\Config;

class DeliveryArea implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var \Dc\Fastway\Helper\Data
     */
    protected $helper;

    /**
     * @param \Dc\Fastway\Helper\Data $helper
     */
    public function __construct(\Dc\Fastway\Helper\Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * 返回南非支持fastway作为发货地的地区
     *
     * @return array|null
     */
    public function toOptionArray()
    {
        return $this->helper->getRegions();
    }
}
