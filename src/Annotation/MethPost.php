<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 17:16
 * Desc:
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class MethPost extends Methods {

    public $methods = ['POST'];

}