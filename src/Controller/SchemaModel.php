<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/5/17
 * Time: 11:01
 * Desc: 定义响应体模型结构,以及获取方法.
 * 本类无实际应用,仅作为swagger文档生成辅助.
 * 获取方法必须以getSchema为前缀,后跟具体结构名称,使用驼峰命名规则的公共静态方法.
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Controller;

use Kph\Helpers\ArrayHelper;
use Kph\Helpers\NumberHelper;
use Kph\Helpers\StringHelper;
use Kph\Objects\BaseObject;

/**
 * Trait SchemaModel
 * @package Hyperf\Apihelper\Controller
 */
trait SchemaModel {


    /**
     * 获取模型结构-单个人员,结构名 Person
     * @return array
     */
    public static function getSchemaPerson(): array {
        $weight = NumberHelper::numberFormat(NumberHelper::randFloat(45, 90));
        return [
            'name'   => StringHelper::randString(3, 5),
            'age'    => intval(StringHelper::randNumber(2)),
            'weight' => floatval($weight),
            'addr'   => StringHelper::randString(16),
            'male'   => boolval(mt_rand(0, 1)),
        ];
    }


    /**
     * 获取模型结构-人员列表,结构名 Persons
     * @return array
     */
    public static function getSchemaPersons(): array {
        // 使用$前缀引用其他结构,首字母大小写都可以,组件将会自动转换为首字母大写的驼峰名称.
        return [
            '$person',
            '$Person',
            '$Person',
        ];
    }


    /**
     * 获取模型结构-单个部门,结构名 Department
     * @return array
     */
    public static function getSchemaDepartment(): array {
        return [
            'name'    => StringHelper::randString(8),
            'manager' => '$Person',
            'members' => '$Persons',
        ];
    }


    /**
     * 获取模型结构-单个公司,结构名 Company
     * @return array
     */
    public static function getSchemaCompany(): array {
        return [
            'name'   => StringHelper::randString(16),
            'leader' => '$Person',
            'groups' => [
                'cn' => [
                    '$department',
                    '$Department',
                    '$Department',
                ],
                'en' => [
                    '$department',
                    '$Department',
                ],
                'us' => [
                    '$department',
                    '$department',
                    '$Department',
                    '$Department',
                ],
            ],
        ];
    }


    /**
     * 获取模型结构-测试人员接口结果,结构名 TestPersons
     * @return array
     */
    public static function getSchemaTestPersons(): array {
        //接口响应模型的外层结构,必须和Response基本响应体一致
        $res         = BaseController::getSchemaResponse();
        $res['data'] = '$Persons';

        return $res;
    }


    /**
     * 获取模型结构-测试公司接口结果,结构名 TestCompany
     * @return array
     */
    public static function getSchemaTestCompany(): array {
        $res         = BaseController::getSchemaResponse();
        $res['data'] = '$Company';

        return $res;
    }


}