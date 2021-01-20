<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/4/16
 * Time: 15:48
 * Desc: 控制器接口
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Controller;

interface ControllerInterface {


    /**
     * 获取结构-基本响应体(键值对数组)
     * @return array
     */
    public static function getSchemaResponse(): array;


    /**
     * 操作成功响应
     * @param array|mixed $data 要返回的数据
     * @param string $msg 提示信息
     * @param array $result 已有的响应结果
     * @return array 最终响应
     */
    public static function doSuccess($data = [], string $msg = 'success', array $result = []): array;


    /**
     * 操作失败响应
     * @param string|int|array|mixed $code 响应码
     * @param array $trans 翻译数据
     * @return array 最终响应
     */
    public static function doFail($code = '400', array $trans = []): array;


    /**
     * 执行验证失败时响应
     * @param string $msg 失败信息
     * @return array 最终响应
     */
    public static function doValidationFail(string $msg = ''): array;


}