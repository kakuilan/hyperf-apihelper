<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/9
 * Time: 15:38
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