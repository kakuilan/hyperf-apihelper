<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 15:14
 * Desc: 验证器接口
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Validatioin;

interface ValidationInterface {
    public function check(array $rules, array $data, object $obj = null, $keyTree = null);

    public function getError();

}

