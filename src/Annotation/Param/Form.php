<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 16:53
 * Desc: form表单数据注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation\Param;

use Hyperf\Apihelper\Annotation\Params;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Form extends Params {

    public $in = 'form';

}