<?php
namespace Dc\Fastway\Model;

use Dc\Fastway\Api\ValidatorInterface;

class Validator implements ValidatorInterface
{
    /**
     * fastway快递单号规则
     */
    const NUMBER_RULE = '/^([a-zA-Z]{2}|[0-9][a-zA-Z])[0-9]{10}+$/';

    /**
     * 验证规则
     *
     * @param string $number
     * @return boolean
     */
    public function isValid($number)
    {
        if (!preg_match(self::NUMBER_RULE, $number)) {
            return false;
        }
        return true;
    }
}
