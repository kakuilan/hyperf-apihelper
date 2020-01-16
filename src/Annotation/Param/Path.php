<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 16:57
 * Desc: restful路径数据注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation\Param;

use Hyperf\Apihelper\Annotation\Params;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Path extends Params {

    public $in = 'path';

}