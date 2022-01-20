<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/3/10
 * Time: 09:43
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper;

use Doctrine\Common\Annotations\AnnotationException;
use Hyperf\Apihelper\Annotation\ApiController;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\ApiVersion;
use Hyperf\Apihelper\Annotation\Methods;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Param\Form;
use Hyperf\Apihelper\Annotation\Param\Header;
use Hyperf\Apihelper\Annotation\Param\Path;
use Hyperf\Apihelper\Annotation\Param\Query;
use Hyperf\Apihelper\Annotation\Params;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Apihelper\Controller\ControllerInterface;
use Hyperf\Apihelper\Exception\ValidationException;
use Hyperf\Apihelper\Swagger\Swagger;
use Hyperf\Apihelper\Validation\Validator;
use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\Exception\ConflictAnnotationException;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\HttpServer\Router\DispatcherFactory as BaseDispatcherFactory;
use Hyperf\HttpServer\Router\RouteCollector;
use Hyperf\Server\Exception\RuntimeException as ServerRuntimeException;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Validation\Concerns\ValidatesAttributes;
use Kph\Consts;
use Kph\Helpers\ArrayHelper;
use Kph\Helpers\StringHelper;
use Kph\Objects\BaseObject;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use Throwable;


/**
 * Class DispatcherFactory
 * @package Hyperf\Apihelper
 */
class DispatcherFactory extends BaseDispatcherFactory {


    /**
     * 临时数据
     * @var array
     */
    private $tmp = [];


    /**
     * @var Config
     */
    private $config;

    /**
     * API文档swagger对象
     * @var Swagger
     */
    public $swagger;


    /**
     * DispatcherFactory constructor.
     */
    public function __construct() {
        $this->initConfig();
        $this->swagger = make(Swagger::class);

        parent::__construct();

        $this->addToRouteCache();
    }


    /**
     * 初始化配置
     */
    private function initConfig() {
        if (is_null($this->config)) {
            $path         = BASE_PATH . '/config/autoload/apihelper.php';
            $conf         = file_exists($path) ? include $path : [];
            $this->config = new Config($conf);
        }
    }


    /**
     * 获取路由规则缓存
     * @return object
     */
    public function getRouteCache() {
        return $this->routeCache;
    }


    /**
     * 初始化注解路由
     * @param array $collector
     * @throws ReflectionException
     */
    protected function initAnnotationRoute(array $collector): void {

        //调用父类注解路由
        parent::initAnnotationRoute($collector);

        //检查基本控制器配置
        $baseCtrlClass = $this->config->get('api.base_controller');
        if (empty($baseCtrlClass)) {
            throw new ServerRuntimeException("api.base_controller can not be empty.");
        } elseif (!class_exists($baseCtrlClass)) {
            throw new ServerRuntimeException("class: {$baseCtrlClass} does not exist.");
        }

        $routes = [];
        foreach ($collector as $className => $metadata) {
            //是否API控制器
            if (isset($metadata['_c'][ApiController::class])) {
                //控制器中间件
                $middlewares = $this->handleMiddleware($metadata['_c']);
                $this->parseController($className);
                $this->handleController($className, $metadata['_c'][ApiController::class], ($metadata['_m'] ?? []), $middlewares);
            }
        }

        $this->swagger->saveJson();
    }


    /**
     * 分析控制器
     * @param string $className
     * @throws AnnotationException
     * @throws ReflectionException
     */
    private function parseController(string $className): void {
        // 检查是否继承自基本控制器,以及控制器接口
        $ctrlObj       = new $className();
        $baseCtrlClass = $this->config->get('api.base_controller');
        if (!($ctrlObj instanceof $baseCtrlClass)) {
            throw new ServerRuntimeException("{$className} must extends from {$baseCtrlClass}.");
        } elseif (!($ctrlObj instanceof ControllerInterface)) {
            throw new ServerRuntimeException("{$baseCtrlClass} must implements " . ControllerInterface::class);
        }

        // 检查控制器前置方法
        $beforeAction = $this->config->get('api.controller_antecedent');
        if (!empty($beforeAction) && method_exists($className, $beforeAction)) {
            $fn = new ReflectionMethod($className, $beforeAction);

            if (!$fn->isPublic()) {
                throw new ServerRuntimeException("{$className}::{$beforeAction} must be public method.");
            }

            //检查该方法的参数是否符合要求
            $paramNum = $fn->getNumberOfParameters();
            if ($paramNum != 1) {
                throw new ServerRuntimeException("{$className}::{$beforeAction} must has only one parameter.");
            }

            //参数类型是否符合
            foreach ($fn->getParameters() as $arg) {
                if ($arg->getType()->getName() != ServerRequestInterface::class) {
                    throw new ServerRuntimeException("{$className}::{$beforeAction} the parameter type must be " . ServerRequestInterface::class);
                }
            }

            //是否有返回值
            if (!$fn->hasReturnType() || $fn->getReturnType()->getName() != ServerRequestInterface::class) {
                throw new ServerRuntimeException("{$className}::{$beforeAction} the return type must be " . ServerRequestInterface::class);
            }
        }

        // 检查控制器拦截方法
        $interceptAction = $this->config->get('api.controller_intercept');
        if (!empty($interceptAction) && method_exists($className, $interceptAction)) {
            $fn = new ReflectionMethod($className, $interceptAction);

            if (!$fn->isPublic()) {
                throw new ServerRuntimeException("{$className}::{$interceptAction} must be public method.");
            }

            //检查该方法的参数是否符合要求
            $paramNum = $fn->getNumberOfParameters();
            if ($paramNum != 3) {
                throw new ServerRuntimeException("{$className}::{$interceptAction} must has three parameter.");
            }

            //参数类型是否符合
            foreach ($fn->getParameters() as $arg) {
                if ($arg->getType()->getName() != 'string') {
                    throw new ServerRuntimeException("{$className}::{$interceptAction} the parameter type must be string");
                }
            }
        }

        // 检查控制器后置方法
        $afterAction = $this->config->get('api.controller_subsequent');
        if (!empty($afterAction) && method_exists($className, $afterAction)) {
            $fn = new ReflectionMethod($className, $afterAction);

            if (!$fn->isPublic()) {
                throw new ServerRuntimeException("{$className}::{$afterAction} must be public method.");
            }

            //检查该方法的参数是否符合要求
            $paramNum = $fn->getNumberOfParameters();
            if ($paramNum != 2) {
                throw new ServerRuntimeException("{$className}::{$interceptAction} must has two parameter.");
            }

            /** @var ReflectionParameter $firstArg */
            $firstArg = $fn->getParameters()[0];
            if ($firstArg->getType()->getName() != ServerRequestInterface::class) {
                throw new ServerRuntimeException("{$className}::{$afterAction} the first parameter type must be " . ServerRequestInterface::class);
            }

            /** @var ReflectionParameter $secondArg */
            $secondArg = $fn->getParameters()[1];
            if ($secondArg->getType()->getName() != ResponseInterface::class) {
                throw new ServerRuntimeException("{$className}::{$afterAction} the second parameter type must be " . ResponseInterface::class);
            }

            //是否有返回值
            if ($fn->returnsReference() !== false || ($fn->hasReturnType() && $fn->getReturnType()->getName() != 'void')) {
                throw new ServerRuntimeException("{$className}::{$afterAction} must have no return value.");
            }
        }

        $refObj  = new ReflectionClass($className);
        $methods = $refObj->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $methodObj) {
            $action = $methodObj->getName();
            $annos  = ApiAnnotation::getMethodMetadata($className, $action);
            if ($methodObj->isStatic() || empty($annos)) {
                continue;
            }

            $rules = [];
            foreach ($annos as $anno) {
                //解析请求参数规则
                if ($anno instanceof Params) {
                    $paramType = BaseObject::getShortName($anno);
                    $fieldName = ApiAnnotation::getFieldByKey($anno->key);
                    $details   = ApiAnnotation::parseDetailsByRule($anno->rule);
                    $details   = Validator::sortDetailRules($details);

                    if (!isset($rules[$paramType])) {
                        $rules[$paramType] = [];
                    }
                    $customs = $rules[$paramType]['customs'] ?? [];
                    $hyperfs = $rules[$paramType]['hyperfs'] ?? [];

                    foreach ($details as $detail) {
                        $ruleName = ApiAnnotation::parseRuleName($detail);

                        //是否本组件的转换器
                        $convMethod = 'conver_' . $ruleName;
                        if (method_exists(Validator::class, $convMethod)) {
                            $customs[$fieldName][] = $detail;
                        }

                        //是否本组件的验证规则
                        $ruleMethod = 'rule_' . $ruleName;
                        if (method_exists(Validator::class, $ruleMethod)) {
                            $customs[$fieldName][] = $detail;
                        }

                        // cb_xxx,调用控制器的方法xxx
                        $controllerMethod = str_replace(Validator::$validateCallbackPrefix, '', $ruleName);
                        if (strpos($ruleName, Validator::$validateCallbackPrefix) !== false && method_exists($className, $controllerMethod)) {
                            //检查该方法
                            $this->checkValidateCallbackAction($className, $controllerMethod);

                            $customs[$fieldName][] = $detail;
                            continue;
                        }

                        // 是否hyperf验证规则
                        $hyperfMethod = 'validate' . StringHelper::toCamelCase($ruleName);
                        if (method_exists(ValidatesAttributes::class, $hyperfMethod)) {
                            $hyperfs[$fieldName][] = $detail;
                        } elseif (!in_array($detail, ArrayHelper::multiArrayValues($customs))) { //非hyperf规则,且非本组件规则
                            throw new ServerRuntimeException("The rule not defined: {$detail} [{$className}::{$action}->$fieldName]");
                        }
                    }

                    ArrayHelper::regularSort($hyperfs, true);
                    ArrayHelper::regularSort($customs, true);

                    $rules[$paramType] = [
                        'hyperfs' => $hyperfs,
                        'customs' => $customs,
                    ];
                }
            }

            $ctrlKey             = $className . Consts::PAAMAYIM_NEKUDOTAYIM . $action;
            $this->tmp[$ctrlKey] = $rules;
        }

    }


    /**
     * 处理匹配的控制器
     * @param string $className 控制器类名
     * @param Controller $controllerAnnos 控制器注解
     * @param array $methodMetadata 方法注解
     * @param array $ctrlMiddlewares 控制器中间件
     * @throws ConflictAnnotationException
     * @throws ReflectionException
     */
    protected function handleController(string $className, Controller $controllerAnnos, array $methodMetadata, array $ctrlMiddlewares = []): void {
        if (empty($methodMetadata)) {
            return;
        }

        $router     = $this->getRouter($controllerAnnos->server);
        $prefix     = rtrim($controllerAnnos->prefix, '/');
        $basePath   = $this->getPrefix($className, $prefix);
        $versions   = ApiAnnotation::getVersionMetadata($className);
        $useVerPath = (bool)$this->config->get('api.use_version_path', true);

        $routeAddeds     = [];
        $addPathToRouter = function ($httpMethod, string $route, $handler, array $options = []) use ($router, $routeAddeds) {
            $arr = [$httpMethod, $route, $handler];
            $key = json_encode($arr);

            if (!in_array($key, $routeAddeds)) {
                array_push($routeAddeds, $key);
                try {
                    $router->addRoute($httpMethod, $route, $handler, $options);
                } catch (Throwable $e) {
                }
            }
        };

        foreach ($methodMetadata as $action => $annos) {
            if (empty($annos)) {
                continue;
            }

            //方法中间件
            $middlewares = array_merge($ctrlMiddlewares, $this->handleMiddleware($annos));
            $middlewares = array_unique($middlewares);

            foreach ($annos as $anno) {
                //添加路由
                if ($anno instanceof Methods) {
                    $path = $basePath . '/' . $action;

                    if (!empty($anno->path)) {
                        $annoPath = $anno->path;
                        if ($annoPath[0] !== '/') {
                            $annoPath = '/' . $annoPath;
                        }

                        //仅仅是路由参数,如 {id}
                        $isRouteParam = preg_match('/^{.*}$/', $annoPath);
                        if ($isRouteParam) {
                            $path = $basePath . '/' . $annoPath;
                        } else {
                            $path = $prefix . $annoPath;
                        }
                    }

                    //路由路径添加版本号
                    if (!empty($versions)) {
                        /** @var ApiVersion $version */
                        foreach ($versions as $version) {
                            $vpath = $useVerPath ? ('/' . $version->group . $path) : $path;
                            $this->swagger->addPath($className, $action, $vpath, $version);
                            $addPathToRouter($anno->methods, $vpath, [$className, $action], ['middleware' => $middlewares,]);
                        }
                    } else {
                        $this->swagger->addPath($className, $action, $path);
                        $addPathToRouter($anno->methods, $path, [$className, $action], ['middleware' => $middlewares,]);
                    }
                }
            }
        }
    }


    /**
     * 检查控制器的验证回调方法
     * @param string $className 控制器类名
     * @param string $action 方法名
     * @throws ReflectionException
     */
    protected function checkValidateCallbackAction(string $className, string $action): void {
        $fn = new ReflectionMethod($className, $action);

        if (!$fn->isPublic()) {
            throw new ServerRuntimeException("{$className}::{$action} must be public method.");
        }

        //检查该方法的参数是否符合要求
        $paramNum = $fn->getNumberOfParameters();
        if ($paramNum != 3) {
            throw new ServerRuntimeException("{$className}::{$action} must has three parameter.");
        }

        $parames = $fn->getParameters();
        // 第一个参数(字段值),类型不固定
        // 第二个参数(字段名),类型string
        // 第三个参数(参数选项),类型array
        if ($parames[1]->getType()->getName() != 'string') {
            $name = $parames[1]->getName();
            throw new ServerRuntimeException("{$className}::{$action} the 2rd parameter[{$name}] type must be string");
        }
        if ($parames[2]->getType()->getName() != 'array') {
            $name = $parames[2]->getName();
            throw new ServerRuntimeException("{$className}::{$action} the 3rd parameter[{$name}] type must be array");
        }

        //是否有返回值
        if (!$fn->hasReturnType() || $fn->getReturnType()->getName() != 'array') {
            throw new ServerRuntimeException("{$className}::{$action} the return type must be array");
        }
    }


    /**
     * 添加路由缓存
     */
    protected function addToRouteCache(): void {
        $cache   = new \stdClass();
        $apianno = new ApiAnnotation();
        foreach ($this->tmp as $key => $item) {
            if (empty($item)) {
                $cache->$key = null;
            } else {
                $cache->$key = new \stdClass();
                foreach ($item as $paramType => $rules) {
                    $cache->$key->$paramType = $rules;
                }
            }
        }

        $apianno->setRouteCache($cache);
        $this->tmp = [];
        $container = ApplicationContext::getContainer();
        $container->set(ApiAnnotation::class, $apianno);
    }


}
