<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
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
     * 分组标签
     * @var string
     */
    public $tag;


    /**
     * 路由前缀
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