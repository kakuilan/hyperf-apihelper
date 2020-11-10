<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/9
 * Time: 16:35
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Middleware;

use Doctrine\Common\Annotations\AnnotationException;
use FastRoute\Dispatcher;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Param\File;
use Hyperf\Apihelper\Annotation\Param\Form;
use Hyperf\Apihelper\Annotation\Param\Header;
use Hyperf\Apihelper\Annotation\Param\Path;
use Hyperf\Apihelper\Annotation\Param\Query;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Apihelper\DispatcherFactory;
use Hyperf\Apihelper\Validation\ValidationInterface;
use Hyperf\Apihelper\Validation\Validator;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Dispatcher\HttpRequestHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Server\Exception\RuntimeException;
use Hyperf\Utils\Context;
use Kph\Consts;
use Kph\Objects\BaseObject;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use ReflectionMethod;
use Throwable;


/**
 * Class ApiValidationMiddleware
 * @package Hyperf\Apihelper\Middleware
 */
class ApiValidationMiddleware extends CoreMiddleware {

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;


    /**
     * @Inject()
     * @var ValidationInterface
     */
    protected $validation;


    /**
     * ApiValidationMiddleware constructor.
     * @param ContainerInterface $container
     * @param HttpResponse $response
     * @param RequestInterface $request
     */
    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request) {
        $this->container = $container;
        $this->response  = $response;
        $this->request   = $request;

        parent::__construct($container, 'http');
    }


    /**
     * 执行处理
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $uri    = $request->getUri();
        $routes = $this->dispatcher->dispatch($request->getMethod(), $uri->getPath());

        if ($routes[0] !== Dispatcher::FOUND) {
            return $handler->handle($request);
        }

        if ($routes[1] instanceof Handler) {
            if (is_string($routes[1]->callback) || is_array($routes[1]->callback)) {
                [$controller, $action] = $this->prepareHandler($routes[1]->callback);
            } else {
                return $handler->handle($request);
            }
        } else {
            [$controller, $action] = $this->prepareHandler($routes[1]);
        }

        $controllerInstance = $this->container->get($controller);

        //执行控制器前置方法
        $globalConf   = $this->container->get(ConfigInterface::class);
        $beforeAction = $globalConf->get('apihelper.api.controller_antecedent');
        if (!empty($beforeAction) && method_exists($controllerInstance, $beforeAction)) {
            //先于动作之前调用
            try {
                $beforeRet = call_user_func_array([$controllerInstance, $beforeAction], [$request]);
            } catch (Throwable $e) {
                throw new RuntimeException($e);
            }

            $request = $beforeRet;
        }

        //检查控制器后置方法
        $subsequentAction = $globalConf->get('apihelper.api.controller_subsequent');
        $subsequentAction = !empty($subsequentAction) && method_exists($controllerInstance, $subsequentAction) ? $subsequentAction : null;

        //确保执行后置方法
        $doAfter = function (ResponseInterface $response) use ($request, $controllerInstance, $subsequentAction): ResponseInterface {
            if ($subsequentAction) {
                try {
                    call_user_func_array([$controllerInstance, $subsequentAction], [$request, $response]);
                } catch (Throwable $e) {
                    throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            }

            return $response;
        };

        $ruleObj       = $this->container->get(ApiAnnotation::class)->getRouteCache();
        $ctrlAct       = $controller . Consts::PAAMAYIM_NEKUDOTAYIM . $action;
        $baseCtrlClass = $globalConf->get('apihelper.api.base_controller');
        if (isset($ruleObj->$ctrlAct)) {
            // 先处理BODY规则
            $typeBody = BaseObject::getShortName(Body::class);
            if (isset($ruleObj->$ctrlAct->$typeBody)) {
                $data = [Body::NAME => $request->getBody()->getContents()];
                [$data, $error] = $this->checkRules($ruleObj->$ctrlAct->$typeBody, $data, [], $controllerInstance);
                if (!empty($error)) {
                    return $doAfter($this->response->json($baseCtrlClass::doValidationFail($error)));
                }
                $request = $request->withBody(new SwooleStream($data[Body::NAME] ?? ''));
            }

            // 各请求方法的数据
            $headers   = array_map(function ($item) {
                return $item[0] ?? null;
            }, $request->getHeaders());
            $queryData = $request->getQueryParams();
            $postData  = $request->getParsedBody();
            $allData   = array_merge($headers, $queryData, $postData);

            $typeHeader = BaseObject::getShortName(Header::class);
            if (isset($ruleObj->$ctrlAct->$typeHeader)) {
                [$data, $error] = $this->checkRules($ruleObj->$ctrlAct->$typeHeader, $headers, $allData, $controllerInstance);
                if (!empty($error)) {
                    return $doAfter($this->response->json($baseCtrlClass::doValidationFail($error)));
                }
            }

            $typePath = BaseObject::getShortName(Path::class);
            if (isset($ruleObj->$ctrlAct->$typePath)) {
                $pathData = $routes[2] ?? [];
                [$data, $error] = $this->checkRules($ruleObj->$ctrlAct->$typePath, $pathData, $allData, $controllerInstance);
                if (!empty($error)) {
                    return $doAfter($this->response->json($baseCtrlClass::doValidationFail($error)));
                }
            }

            $typeQuery = BaseObject::getShortName(Query::class);
            if (isset($ruleObj->$ctrlAct->$typeQuery)) {
                [$data, $error] = $this->checkRules($ruleObj->$ctrlAct->$typeQuery, $queryData, $allData, $controllerInstance);
                if (!empty($error)) {
                    return $doAfter($this->response->json($baseCtrlClass::doValidationFail($error)));
                }
                $request = $request->withQueryParams($data);
            }

            $typeForm = BaseObject::getShortName(Form::class);
            if (isset($ruleObj->$ctrlAct->$typeForm)) {
                [$data, $error] = $this->checkRules($ruleObj->$ctrlAct->$typeForm, $postData, $allData, $controllerInstance);
                if (!empty($error)) {
                    return $doAfter($this->response->json($baseCtrlClass::doValidationFail($error)));
                }
                $request = $request->withParsedBody($data);
            }

            //文件上传
            $typeFile = BaseObject::getShortName(File::class);
            if (isset($ruleObj->$ctrlAct->$typeFile)) {
                [$data, $error] = $this->checkRules($ruleObj->$ctrlAct->$typeFile, $request->getUploadedFiles(), $allData, $controllerInstance);
                if (!empty($error)) {
                    return $doAfter($this->response->json($baseCtrlClass::doValidationFail($error)));
                }
                $request = $request->withUploadedFiles($data);
            }
        }

        Context::set(ServerRequestInterface::class, $request);

        //执行控制器拦截方法
        $interceptAction = $globalConf->get('apihelper.api.controller_intercept');
        if (!empty($interceptAction) && method_exists($controllerInstance, $interceptAction)) {
            //先于动作之前调用
            try {
                $ret = call_user_func_array([$controllerInstance, $interceptAction], [$controller, $action, ($routes[1]->route ?? '')]);
                //若返回非空的数组或字符串,则终止后续动作的执行
                if (!empty($ret) && (is_array($ret) || is_string($ret))) {
                    return $doAfter(is_array($ret) ? $this->response->json($ret) : $this->response->raw($ret));
                }
            } catch (Throwable $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $response = $handler->handle($request);

        return $doAfter($response);
    }


    /**
     * 执行规则检查
     * @param array $rules
     * @param array $data
     * @param array $otherData
     * @param object $controller
     * @return array
     */
    public function checkRules(array $rules, array $data, array $otherData, object $controller): array {
        [$validatedData, $errors] = $this->validation->validate($rules, $data, $otherData, $controller);
        $error = empty($errors) ? '' : current($errors);

        return [$validatedData, $error];
    }

}