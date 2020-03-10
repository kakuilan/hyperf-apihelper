<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 09:43
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper;

use Doctrine\Common\Annotations\AnnotationException;
use Hyperf\Apihelper\Annotation\ApiController;
use Hyperf\Apihelper\Swagger\Swagger;
use Hyperf\Di\Exception\ConflictAnnotationException;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\HttpServer\Router\DispatcherFactory as BaseDispatcherFactory;

/**
 * Class DispatcherFactory
 * @package Hyperf\Apihelper
 */
class DispatcherFactory extends BaseDispatcherFactory {


    /**
     * 路由规则缓存
     * @var array
     */
    public static $routeCache = [];


    /**
     * @var Swagger
     */
    public $swagger;


    /**
     * DispatcherFactory constructor.
     */
    public function __construct() {
        $this->swagger = new Swagger();
        parent::__construct();
    }


}