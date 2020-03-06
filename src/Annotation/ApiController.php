<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/6
 * Time: 16:11
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation;

use Hyperf\HttpServer\Annotation\Controller;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
class ApiController extends Controller {


    /**
     * @var string 分组标签
     */
    public $tag;


    /**
     * @var string
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