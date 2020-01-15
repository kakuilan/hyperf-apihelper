<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 15:35
 * Desc: 响应注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

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
     * @var string 响应json结构
     */
    public $schema;


    public function __construct($value = null) {
        parent::__construct($value);
        if (is_array($this->description)) {
            $this->description = json_encode($this->description, JSON_UNESCAPED_UNICODE);
        }
        $this->makeSchema();
    }


    public function makeSchema() {
        //TODO 生成json结构
    }

}