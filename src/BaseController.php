<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/31
 * Time: 13:55
 * Desc: 基础控制器
 */

declare(strict_types=1);

namespace Hyperf\Apihelper;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController {

    /**
     * 全局容器Hyperf\Di\Containe
     * @Inject
     * @var ContainerInterface
     */
    protected $container;


    /**
     * @Inject
     * @var RequestInterface
     */
    protected $request;


    /**
     * @Inject
     * @var ResponseInterface
     */
    protected $response;


    /**
     * 初始化方法(在具体动作之前执行)
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    public function initialization(ServerRequestInterface $request): ServerRequestInterface {
        //自定义处理逻辑,如 将数据存储到$request属性中
        //$request = $request->withAttribute('test', 'hello world');
        //在动作里面获取数据
        //$test = $request->getAttribute('test');

        return $request;
    }


}