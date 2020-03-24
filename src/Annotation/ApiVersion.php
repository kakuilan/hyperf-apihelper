<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/6
 * Time: 16:27
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class ApiVersion extends AbstractAnnotation {

    /**
     * @var string 分组名称,只能有英文、数字和下划线组成
     */
    public $group;


    /**
     * @var string 分组版本描述
     */
    public $description = '';


}