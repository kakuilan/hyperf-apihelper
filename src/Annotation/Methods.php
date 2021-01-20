<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/3/6
 * Time: 16:28
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation;

use Hyperf\HttpServer\Annotation\Mapping;


/**
 * Class Methods
 * @package Hyperf\Apihelper\Annotation
 */
class Methods extends Mapping {


    /**
     * @var array 请求方法
     */
    public $methods;


    /**
     * @var string 请求路径
     */
    public $path;


    /**
     * @var string 接口摘要
     */
    public $summary;


    /**
     * @var string 接口描述
     */
    public $description;


    /**
     * @var bool 是否弃用
     */
    public $deprecated;


    /**
     * Methods constructor.
     * @param mixed $value
     */
    public function __construct($value = null) {
        parent::__construct($value);
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (property_exists($this, $key)) {
                    if ($key == 'deprecated') {
                        $val = boolval($val);
                    }
                    $this->{$key} = $val;
                }
            }
        }
    }


}