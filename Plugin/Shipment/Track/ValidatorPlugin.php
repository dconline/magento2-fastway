<?php
namespace Dc\Fastway\Plugin\Shipment\Track;

use Dc\Fastway\Model\Carrier;

class ValidatorPlugin
{
    /**
     * @var \Dc\Fastway\Api\ValidatorInterface
     */
    protected $validator;

    /**
     * @param \Dc\Fastway\Api\ValidatorInterface $validator
     */
    public function __construct(
        \Dc\Fastway\Api\ValidatorInterface $validator
    ) {
        $this->validator = $validator;
    }

    /**
     * 增加验证单号格式方法
     *
     * @param \Magento\Sales\Model\Order\Shipment\Track\Validator $subject
     * @param \Closure $proceed
     * @param \Magento\Sales\Model\Order\Shipment\Track $track
     * @return null|array
     */
    public function aroundValidate(\Magento\Sales\Model\Order\Shipment\Track\Validator $subject, \Closure $proceed, \Magento\Sales\Model\Order\Shipment\Track $track)
    {
        // 先执行原先的验证
        $errors = $proceed($track);
        // 当前使用运输方式
        if (!$errors && $track->getCarrierCode() === Carrier::CODE) {
            // 快递单号
            $number = $track->getTrackNumber();
            // 验证规则，返回信息
            if (!$this->validator->isValid($number)) {
                $errors['track_number'] = __('Invalid track number is %1 ', $number);
            }
        }
        return $errors;
    }
}
