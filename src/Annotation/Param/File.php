<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/24
 * Time: 17:36
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation\Param;

use Hyperf\Apihelper\Annotation\Params;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class File extends Params {

    public $in = 'formData';

}