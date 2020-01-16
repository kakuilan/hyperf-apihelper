<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 17:18
 * Desc: header数据注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation\Param;

use Hyperf\Apihelper\Annotation\Params;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Header extends Params {

    public $in = 'header';

}