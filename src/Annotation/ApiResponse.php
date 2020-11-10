<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/6
 * Time: 16:13
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;
use Kph\Helpers\ArrayHelper;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ApiResponse extends AbstractAnnotation {


    /**
     * @var string httpCode
     */
    public $code;


    /**
     * @var string 响应描述
     */
    public $description;


    /**
     * @var array 响应json结构,将自动转数组
     */
    public $schema;


    /**
     * @var string 指明schema中要引用的字段名,默认为data
     */
    public $refKey = 'data';


    /**
     * @var string 指明schema中引用字段的值(模型名称)
     */
    public $refValue;


    /**
     * ApiResponse constructor.
     * @param null $value
     */
    public function __construct($value = null) {
        parent::__construct($value);
        if (is_array($this->description)) {
            $this->description = json_encode($this->description, JSON_UNESCAPED_UNICODE);
        }
    }

}