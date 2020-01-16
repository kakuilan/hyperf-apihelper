<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 16:56
 * Desc: query数据注解
 */

namespace Hyperf\Apihelper\Annotation\Param;

use Hyperf\Apihelper\Annotation\Params;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Query extends Params {

    public $in = 'query';

}