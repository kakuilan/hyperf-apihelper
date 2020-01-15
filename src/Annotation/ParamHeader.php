<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 17:18
 * Desc: header数据注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ParamHeader extends Params {

    public $in = 'header';

}