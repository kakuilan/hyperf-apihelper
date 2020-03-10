<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/9
 * Time: 16:43
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper;

use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Kph\Objects\BaseObject;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController extends BaseObject {


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
     * 初始化方法(在具体动作之前执行).
     * 不会中止后续具体动作的执行.
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
    //    public function initialization(ServerRequestInterface $request): ServerRequestInterface {
    //        //自定义处理逻辑,如 将数据存储到$request属性中
    //        $request = $request->withAttribute('test', 'hello world');
    //        //在动作里面获取数据
    //        $test = $request->getAttribute('test');
    //
    //        return $request;
    //    }


    /**
     * 拦截方法(在具体动作之前执行).
     * 当返回非空的数组或字符串时,将中止后续具体动作的执行.
     * @param string $controller 控制器类名
     * @param string $action 方法名(待执行的动作)
     * @param string $route 路由(url)
     * @return array|null
     */
    //    public function interceptor(string $controller, string $action, string $route) {
    //        if (false) {
    //            return ApiResponse::doFail(400);
    //        }
    //
    //        return null;
    //    }


}