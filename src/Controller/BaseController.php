<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/4/16
 * Time: 16:32
 * Desc: 基本控制器
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Controller;

use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Kph\Helpers\ArrayHelper;
use Kph\Objects\BaseObject;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController extends BaseObject implements ControllerInterface {

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
     * @var array 接口响应的基本json结构
     */
    protected static $baseSchema = [
        'status' => true,
        'msg'    => 'success',
        'code'   => 200,
        'data'   => [],
    ];


    /**
     * 获取响应体结构
     * @return array
     */
    public static function getResponseSchema(): array {
        return self::$baseSchema;
    }


    /**
     * 处理接口成功数据
     * @param array|mixed $data 要返回的数据
     * @param string $msg 提示信息
     * @param array $result 已有的响应结果
     * @return array
     */
    public static function doSuccess($data = [], string $msg = 'success', array $result = []): array {
        if (empty($result)) {
            $result = self::getResponseSchema();
        }

        if (is_bool($data)) {
            $result['data'] = [];
        } elseif (is_object($data)) {
            $data           = ArrayHelper::object2Array($data);
            $result['data'] = array_merge((array)$result['data'], $data);
        } elseif (is_array($data)) {
            $result['data'] = array_merge((array)$result['data'], $data);
        } else {
            $result['data'] = strval($data);
        }

        $result = [
            'status' => true,
            'msg'    => $msg,
            'code'   => 200,
            'data'   => $result['data'],
        ];

        return $result;
    }


    /**
     * 处理接口失败数据
     * @param string|int|array|mixed $code 错误码(如400);或错误信息数组[错误码,提示信息],如 [400, '操作失败']
     * @param array $trans 翻译数组
     * @return array
     */
    public static function doFail($code = '400', array $trans = []): array {
        if (is_array($code)) {
            $msg  = end($code);
            $code = reset($code);
        } elseif (is_numeric($code)) {
            $msg = trans("apihelper.{$code}", $trans);
        } else {
            $msg = $code;
        }

        $codeNo = ($code != $msg && is_numeric($code)) ? intval($code) : 400;

        $result = [
            'status' => false,
            'msg'    => strval($msg),
            'code'   => $codeNo,
            'data'   => [],
        ];

        return $result;
    }


    /**
     * 执行验证失败时响应
     * @param string $msg 失败信息
     * @return array
     */
    public static function doValidationFail(string $msg = ''): array {
        $code = empty($msg) ? 412 : [400, $msg];

        return self::doFail($code);
    }


    /**
     * 初始化方法(在具体动作之前执行).
     * 不会中止后续具体动作的执行.
     * @param ServerRequestInterface $request
     * @return ServerRequestInterface
     */
//    public function initialization(ServerRequestInterface $request): ServerRequestInterface {
//        //自定义处理逻辑,如 将数据存储到$request属性中
//        $request = $request->withAttribute('test', 'hello world');
//
//        //然后在具体动作里面获取数据
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
//            return self::doFail();
//        }
//
//        return null;
//    }

}