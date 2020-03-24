<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/9
 * Time: 15:45
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation\Param;

use Hyperf\Apihelper\Annotation\Params;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Query extends Params {

    public $in = 'query';

}