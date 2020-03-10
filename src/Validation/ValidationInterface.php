<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/9
 * Time: 16:09
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Validation;

/**
 * Interface ValidationInterface
 * @package Hyperf\Apihelper\Validation
 */
interface ValidationInterface {


    /**
     * 执行验证,并返回经处理过的数据
     * @param array $rules 规则数组
     * @param array $data 当前请求方式的数据
     * @param array $otherData 其他数据
     * @param object $controller 控制器对象
     * @return array
     */
    public function validate(array $rules, array $data, array $otherData = [], object $controller = null): array;


    /**
     * 获取错误
     * @return array
     */
    public function getError(): array;

}