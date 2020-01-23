<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/23
 * Time: 13:55
 * Desc:
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation\Method;

use Hyperf\Apihelper\Annotation\Methods;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Patch extends Methods {

    public $methods = ['PATCH'];

}