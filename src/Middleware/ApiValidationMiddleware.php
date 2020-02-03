<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/16
 * Time: 15:53
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Middleware;

use Doctrine\Common\Annotations\AnnotationException;
use Exception;
use FastRoute\Dispatcher;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Param\Form;
use Hyperf\Apihelper\Annotation\Param\Header;
use Hyperf\Apihelper\Annotation\Param\Path;
use Hyperf\Apihelper\Annotation\Param\Query;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Apihelper\Validation\Validation;
use Hyperf\Apihelper\Validation\ValidationInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Server\Exception\RuntimeException;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use ReflectionMethod;

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
            [$controller, $action] = $this->prepareHandler($routes[1]->callback);
        } else {
            [$controller, $action] = $this->prepareHandler($routes[1]);
        }

        $controllerInstance = $this->container->get($controller);

        //执行控制器前置方法
        $globalConf   = $this->container->get(ConfigInterface::class);
        $beforeAction = $globalConf->get('apihelper.api.controller_antecedent');
        if (!empty($beforeAction) && method_exists($controllerInstance, $beforeAction)) {
            $fn = new ReflectionMethod($controllerInstance, $beforeAction);

            if (!$fn->isPublic()) {
                $cls = get_class($controllerInstance);
                throw new RuntimeException("{$cls}::{$beforeAction} must be public method.");
            }

            //检查该方法的参数是否符合要求
            $paramNum = $fn->getNumberOfParameters();
            if ($paramNum != 1) {
                $cls = get_class($controllerInstance);
                throw new RuntimeException("{$cls}::{$beforeAction} must has only one parameter.");
            }

            //参数类型是否符合
            foreach ($fn->getParameters() AS $arg) {
                if ($arg->getType()->getName() != ServerRequestInterface::class) {
                    $cls = get_class($controllerInstance);
                    throw new RuntimeException("{$cls}::{$beforeAction} the parameter type must be " . ServerRequestInterface::class);
                }
            }

            //是否有返回值
            if (!$fn->hasReturnType() || $fn->getReturnType()->getName() != ServerRequestInterface::class) {
                $cls = get_class($controllerInstance);
                throw new RuntimeException("{$cls}::{$beforeAction} the return type must be " . ServerRequestInterface::class);
            }

            //先于动作之前调用
            try {
                $beforeRet = call_user_func_array([$controllerInstance, $beforeAction], [$request]);
            } catch (Exception $e) {
                throw new RuntimeException($e);
            }

            $request = $beforeRet;
        }

        $annotations = ApiAnnotation::methodMetadata($controller, $action);
        $headerRules = [];
        $pathRules   = [];
        $queryRules  = [];
        $bodyRules   = [];
        $formRules   = [];

        foreach ($annotations as $annotation) {
            if ($annotation instanceof Header) {
                $headerRules[$annotation->key] = $annotation->rule;
            }
            if ($annotation instanceof Path) {
                $pathRules[$annotation->key] = $annotation->rule;
            }
            if ($annotation instanceof Query) {
                $queryRules[$annotation->key] = $annotation->rule;
            }
            if ($annotation instanceof Body) {
                $bodyRules = $annotation->rules;
            }
            if ($annotation instanceof Form) {
                $formRules[$annotation->key] = $annotation->rule;
            }
        }

        $headers = $request->getHeaders();
        $queryData = $request->getQueryParams();
        $postData = $request->getParsedBody();
        $allData = array_merge($headers, $queryData, $postData);

        if ($headerRules) {
            $headers = array_map(function ($item) {
                return $item[0];
            }, $headers);
            [$data, $error] = $this->check($headerRules, $headers, $allData, $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
        }

        if ($pathRules) {
            $pathData = $routes[2] ?? [];
            [$data, $error] = $this->check($pathRules, $pathData, $allData, $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
        }

        if ($queryRules) {
            [$data, $error] = $this->check($queryRules, $queryData, $allData, $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
            $request = $request->withQueryParams($data);
        }

        if ($bodyRules) {
            [$data, $error] = $this->check($bodyRules, (array)json_decode($request->getBody()->getContents(), true), [], $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
            $request = $request->withBody(new SwooleStream(json_encode($data)));
        }

        if ($formRules) {
            [$data, $error] = $this->check($formRules, $postData, $allData, $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
            $request = $request->withParsedBody($data);
        }

        //TODO withUploadedFiles vendor/hyperf/http-message/src/Server/Request.php

        Context::set(ServerRequestInterface::class, $request);
        return $handler->handle($request);
    }


    /**
     * 检查
     * @param $rules
     * @param $data
     * @param $otherData
     * @param $controllerInstance
     * @return array
     */
    public function check($rules, $data, $otherData, $controllerInstance) {
        $validatedData = $this->validation->check($rules, $data, $otherData, $controllerInstance);
        $errors        = $this->validation->getError();
        $error         = empty($errors) ? '' : current($errors);

        return [$validatedData, $error];
    }

}