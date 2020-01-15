<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 16:53
 * Desc: form表单数据注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ParamForm extends Params {

    public $in = 'form';

}