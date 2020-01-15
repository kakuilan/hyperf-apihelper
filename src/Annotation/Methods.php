<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 17:04
 * Desc: 请求方法
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;
use Hyperf\HttpServer\Annotation\Mapping;

abstract class Methods extends Mapping {


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


    public function __construct($value = null) {
        parent::__construct($value);
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (property_exists($this, $key)) {
                    if($key=='deprecated') {
                        $val = boolval($val);
                    }
                    $this->{$key} = $val;
                }
            }
        }
    }





}