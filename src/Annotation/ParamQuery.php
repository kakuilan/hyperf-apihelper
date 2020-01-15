<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 16:56
 * Desc: query数据注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ParamQuery extends Params {

    public $in = 'query';

}