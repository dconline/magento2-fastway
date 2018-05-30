<?php
namespace Dc\Fastway\Api;

interface ValidatorInterface
{
    /**
     * 验证规则
     *
     * @param string $number
     * @return boolean
     */
    public function isValid($number);
}
