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

use FastRoute\Dispatcher;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Param\Form;
use Hyperf\Apihelper\Annotation\Param\Header;
use Hyperf\Apihelper\Annotation\Param\Query;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\CoreMiddleware;
use Hyperf\HttpServer\Router\Handler;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\Context;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
     * @var \Hyperf\Apihelper\Validation\ValidationInterface
     */
    protected $validation;


    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request) {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;

        parent::__construct($container, 'http');
    }


    /**
     * 执行处理
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $uri = $request->getUri();
        $routes = $this->dispatcher->dispatch($request->getMethod(), $uri->getPath());
        if ($routes[0] !== Dispatcher::FOUND) {
            return $handler->handle($request);
        }

        if ($routes[1] instanceof Handler) {
            [$controller, $action] = [
                $routes[1]->callback[0],
                $routes[1]->callback[1]
            ];
        } else {
            [$controller, $action] = $this->prepareHandler($routes[1]);
        }

        $controllerInstance = $this->container->get($controller);
        $annotations = ApiAnnotation::methodMetadata($controller, $action);
        $headerRules = [];
        $queryRules = [];
        $bodyRules = [];
        $formRules = [];

        foreach ($annotations as $annotation) {
            if ($annotation instanceof Header) {
                $headerRules[$annotation->key] = $annotation->rule;
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

        if ($headerRules) {
            $headers = $request->getHeaders();
            $headers = array_map(function($item) {
                return $item[0];
            }, $headers);
            [$data, $error] = $this->check($headerRules, $headers, $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
        }

        if ($queryRules) {
            [$data, $error] = $this->check($queryRules, $request->getQueryParams(), $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
            Context::set(ServerRequestInterface::class, $request->withQueryParams($data));
        }

        if ($bodyRules) {
            [$data, $error] = $this->check($bodyRules, (array)json_decode($request->getBody()->getContents(), true), $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
            Context::set(ServerRequestInterface::class, $request->withBody(new SwooleStream(json_encode($data))));
        }

        if ($formRules) {
            [$data, $error] = $this->check($formRules, $request->getParsedBody(), $controllerInstance);
            if ($data === false) {
                return $this->response->json(ApiResponse::doFail([400, $error]));
            }
            Context::set(ServerRequestInterface::class, $request->withParsedBody($data));
        }

        return $handler->handle($request);
    }


    /**
     * 检查
     * @param $rules
     * @param $data
     * @param $controllerInstance
     * @return array
     */
    public function check($rules, $data, $controllerInstance) {
        $validatedData = $this->validation->check($rules, $data, $controllerInstance);
        $errors = $this->validation->getError();
        $error = empty($errors) ? '' : current($errors);

        return [$validatedData, $error];
    }

}