<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 15:32
 * Desc: API控制器注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

use Hyperf\HttpServer\Annotation\Controller;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class ApiController extends Controller {

    public $tag;

    /**
     * @var null|string
     */
    public $prefix = '';

    /**
     * @var string
     */
    public $server = 'http';

    /**
     * @var string
     */
    public $description = '';

}