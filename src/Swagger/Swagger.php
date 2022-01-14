<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/3/10
 * Time: 09:40
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Swagger;

use Doctrine\Common\Annotations\AnnotationException;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\ApiVersion;
use Hyperf\Apihelper\Annotation\Methods;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Params;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Apihelper\Controller\BaseController;
use Hyperf\Apihelper\Controller\ControllerInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Server\Exception\RuntimeException;
use Hyperf\Utils\ApplicationContext;
use Kph\Helpers\ArrayHelper;
use Kph\Helpers\ConvertHelper;
use Kph\Helpers\DirectoryHelper;
use Kph\Helpers\FileHelper;
use Kph\Helpers\OsHelper;
use Kph\Helpers\StringHelper;
use Kph\Helpers\UrlHelper;
use Kph\Helpers\ValidateHelper;
use Kph\Objects\BaseObject;
use ReflectionException;
use ReflectionMethod;

/**
 * Class Swagger
 * @package Hyperf\Apihelper\Swagger
 */
class Swagger {

    /**
     * 全局配置
     * @var ConfigInterface
     */
    public $confGlobal;


    /**
     * swagger配置
     * @var mixed
     */
    public $confSwagger;


    /**
     * 版本号分组数组,如[ 'v1'=>['name'=>'v1', 'description'=>'some', 'paths'=>[]] ]
     * @var array
     */
    public $groups = [];


    /**
     * Swagger constructor.
     */
    public function __construct() {
        $this->confGlobal  = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $this->confSwagger = $this->confGlobal->get('apihelper.swagger');

        $this->initDefinitions();
    }


    /**
     * 初始化常用模型定义
     */
    public function initDefinitions() {
        $this->confSwagger['definitions']   = [];
        $this->confSwagger['schemaMethods'] = [];

        //基本响应体
        /** @var \Hyperf\Apihelper\Controller\ControllerInterface $baseCtrlClass */
        $baseCtrlClass = $this->confGlobal->get('apihelper.api.base_controller');
        if (empty($baseCtrlClass)) {
            throw new RuntimeException("apihelper.api.base_controller can not be empty.");
        } elseif (!method_exists($baseCtrlClass, 'getSchemaResponse')) {
            throw new RuntimeException("{$baseCtrlClass} must implements " . ControllerInterface::class);
        }

        $baseSchema = call_user_func([$baseCtrlClass, 'getSchemaResponse']);
        $properties = [];
        foreach ($baseSchema as $key => $val) {
            $item = [
                'type'    => ApiAnnotation::getTypeByValue($val),
                'example' => is_array($val) ? [] : (is_object($val) ? new \stdClass() : $val),
            ];

            if (in_array($item['type'], ['object', 'array'])) {
                $item['items'] = new \stdClass(); //数组元素是任意类型
            }

            if ($item['type'] === 'integer') {
                $item['format'] = 'int64';
            }

            $properties[$key] = $item;
        }

        $response = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        $this->confSwagger['definitions']['Response'] = $response;

        //基本控制器中定义的其他结构模型
        $methods = self::getSchemaMethods($baseCtrlClass);
        foreach ($methods as $method) {
            $this->confSwagger['schemaMethods'][$method] = $baseCtrlClass;
            if ($method === 'getSchemaResponse') { //忽略外层基本结构模型
                continue;
            }

            $this->parseSchemaModelByName($baseCtrlClass, $method, $methods);
        }
    }


    /**
     * 根据名称解析响应结构模型
     * @param string $controller 控制器类
     * @param string $schemaStr 结构(或方法)名称
     * @param array $methods 控制器中定义的结构方法数组
     * @return mixed|array|object
     * @throws ReflectionException
     */
    public function parseSchemaModelByName(string $controller, string $schemaStr, array $methods = []) {
        if (empty($methods)) {
            $methods = self::getSchemaMethods($baseCtrlClass);
        }

        [$schemaName, $schemaMethod] = self::extractSchemaNameMethod($schemaStr);
        $callback   = "{$controller}::{$schemaMethod}";
        $schemaData = call_user_func($callback);
        if (!is_array($schemaData)) { //结构模型方法的返回值必须是数组
            throw new RuntimeException("{$callback} the return value type must be an array.");
        }

        $properties = self::parseSchemaNestedData($schemaData, $methods);

        //添加到swagger模型定义列表
        $type        = ApiAnnotation::getTypeByValue($schemaData);
        $schemaModel = [
            'type' => $type,
        ];
        if ($type == 'array') {
            $schemaModel['items'] = current($properties);
        } elseif ($type == 'object') {
            $schemaModel['properties'] = $properties;
        }

        $this->confSwagger['definitions'][$schemaName] = $schemaModel;

        return $schemaData;
    }


    /**
     * 根据结构名获取已存在的definition
     * @param string $schemaName
     * @return array
     */
    public function getDefinitionBySchemaName(string $schemaName): array {
        return $this->confSwagger['definitions'][$schemaName] ?? [];
    }


    /**
     * 解析模型嵌套数据
     * @param array $arr
     * @param array $methods
     * @return array
     */
    public static function parseSchemaNestedData(array $arr, array $methods): array {
        ArrayHelper::regularSort($arr);
        foreach ($arr as &$val) {
            $oriVal = $val;
            $newVal = null;
            if (is_array($val) && !empty($val)) {
                $ret      = self::parseSchemaNestedData($val, $methods);
                $subitems = implode('', ArrayHelper::multiArrayValues($ret));

                //一维数组,且元素是引用结构
                if (ValidateHelper::isOneDimensionalArray($val) && stripos($subitems, 'definitions')) {
                    $newVal = [
                        'type'  => 'array',
                        'items' => current($ret),
                    ];
                }
                $val = $ret;
            } elseif (is_string($val) && ValidateHelper::startsWith($val, '$')) {
                $str = self::turnRefSchema2Name($val);
                if (ValidateHelper::isAlphaNumDash($str)) {
                    [$schemaName, $schemaMethod] = self::extractSchemaNameMethod($str);
                    if (in_array($schemaMethod, $methods)) {
                        $newVal = [
                            '$ref' => "#/definitions/{$schemaName}",
                        ];
                    }
                }
            }

            if (is_null($newVal)) {
                $type   = ApiAnnotation::getTypeByValue($val);
                $newVal = [
                    'type' => $type,
                ];
                if ($type == 'integer') {
                    $newVal['format'] = 'int64';
                } elseif ($type == 'object') {
                    $newVal['properties'] = $val;
                } elseif ($type == 'array') {
                    $newVal['items'] = new \stdClass(); //数组元素是任意类型
                }

                $newVal['example'] = self::parseExample($oriVal, $methods);
            }

            $val = $newVal;
        }

        return $arr;
    }


    /**
     * 将值解析为Example
     * @param mixed $val
     * @param array $methods
     * @return array|string|mixed
     */
    public static function parseExample($val, array $methods = []) {
        if (is_object($val) && !ValidateHelper::isEmptyObject($val)) {
            $val = ConvertHelper::object2Array($val);
        }

        if (is_array($val) && !empty($val)) {
            ArrayHelper::regularSort($val);
            foreach ($val as &$item) {
                $item = self::parseExample($item, $methods);
            }
        } elseif (is_string($val) && ValidateHelper::startsWith($val, '$')) {
            $str = self::turnRefSchema2Name($val);
            if (ValidateHelper::isAlphaNumDash($str)) {
                [$schemaName, $schemaMethod] = self::extractSchemaNameMethod($str);
                if (in_array($schemaMethod, $methods)) {
                    $val = BaseController::getDefaultDataBySchemaName($schemaName);
                    if (is_array($val)) {
                        $val = self::parseExample($val, $methods);
                    }
                }
            }
        }

        return $val;
    }


    /**
     * 抽取结构名和方法名
     * @param string $str
     * @return array
     */
    public static function extractSchemaNameMethod(string $str): array {
        $schemaMethod = $schemaName = '';
        if (!empty($str)) {
            if (ValidateHelper::startsWith($str, ApiAnnotation::$schemaMethodPrefix)) {
                $schemaMethod = $str;
                $schemaName   = str_replace(ApiAnnotation::$schemaMethodPrefix, '', $str);
            } else {
                // 处理带引用的结构名,如 $Person => Person
                $schemaName   = self::turnRefSchema2Name($str);
                $schemaMethod = ApiAnnotation::$schemaMethodPrefix . $schemaName;
            }
        }

        return [$schemaName, $schemaMethod];
    }


    /**
     * 获取控制器中结构模型方法列表
     * @param string $controller 控制器类
     * @return array
     * @throws ReflectionException
     */
    public static function getSchemaMethods(string $controller): array {
        $methods = BaseObject::getClassMethods($controller, ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
        $methods = array_filter($methods, function ($v) {
            // 以'getSchema'为前缀的公共静态方法
            return ValidateHelper::startsWith($v, ApiAnnotation::$schemaMethodPrefix);
        });

        return $methods;
    }


    /**
     * 转换路径中的路由参数,形如{x}
     * @param string $path
     * @return string
     */
    public static function turnPath(string $path): string {
        $path = str_replace(['{', '}'], '', $path);
        return $path;
    }


    /**
     * 将引用模型转换为名称(如 $name => Name)
     * @param string $val
     * @return string
     */
    public static function turnRefSchema2Name(string $val): string {
        if (!empty($val)) {
            $val = StringHelper::removeBefore($val, '$', true);
            $val = ucfirst(StringHelper::toCamelCase($val));
        }

        return $val;
    }


    /**
     * 将名称转换为引用(如 name => $name)
     * @param string $val
     * @return string
     */
    public static function turnName2RefSchema(string $val): string {
        if (!empty($val) && !ValidateHelper::startsWith($val, '$')) {
            $val = '$' . $val;
        }

        return $val;
    }


    /**
     * 添加接口路径信息
     * @param string $className
     * @param string $methodName
     * @param string $path
     * @param mixed $version ApiVersion
     * @throws AnnotationException
     */
    public function addPath(string $className, string $methodName, string $path, $version = null): void {
        //获取类文件的注解信息
        $classAnnotation   = ApiAnnotation::getClassMetadata($className);
        $methodAnnotations = ApiAnnotation::getMethodMetadata($className, $methodName);

        $params    = [];
        $paths     = [];
        $responses = [];

        //检查版本号是否合法
        $hasVersion = is_object($version) && ($version instanceof ApiVersion) && !empty($version->group);
        if ($hasVersion) {
            if (!ApiAnnotation::isVersion($version->group)) {
                throw new RuntimeException("Version group name can only be in english, numerals, and underscores:{$className}[{$version->group}]");
            }
        }

        //先处理该控制器类中定义的结构模型
        $methods = self::getSchemaMethods($className);
        foreach ($methods as $method) {
            if (in_array($method, array_keys($this->confSwagger['schemaMethods']))) {
                continue;
            }

            $this->parseSchemaModelByName($className, $method, $methods);
            $this->confSwagger['schemaMethods'][$method] = $className;
        }

        /** @var \Hyperf\Apihelper\Annotation\Methods $reqMethod */
        $reqMethod = null;
        foreach ($methodAnnotations as $item) {
            if ($item instanceof Methods) {
                $reqMethod = $item;
            } elseif ($item instanceof Params) {
                array_push($params, $item);
            } elseif ($item instanceof ApiResponse) {
                array_push($responses, $item);
            }
        }

        //分组标签
        $tagName = $classAnnotation->tag ?: $className;
        $tagInfo = ['name' => $tagName, 'description' => $classAnnotation->description,];
        $method  = strtolower($reqMethod->methods[0]);

        $this->confSwagger['tags'][$tagName] = $tagInfo;

        $paths[$path][$method] = [
            'tags'        => [$tagName,],
            'summary'     => $reqMethod->summary,
            'parameters'  => $this->makeParameters($params, $path), //接口默认接收的MIME类型
            'consumes'    => ['application/x-www-form-urlencoded', 'application/json', 'multipart/form-data',], //接口默认的响应类型
            'produces'    => ['application/json',],
            'responses'   => $this->makeResponses($responses, $path, $method),
            'description' => $reqMethod->description,
        ];

        if ($hasVersion) {
            $this->confSwagger['version_tags'][$version->group][$tagName] = $tagInfo;
            $this->addGroupInfo($version->group, $version->description, $paths);
        } else {
            $this->confSwagger['paths'] = array_merge_recursive(($this->confSwagger['paths'] ?? []), $paths);
        }
    }


    /**
     * 添加版本分组信息
     * @param string $name 版本名称
     * @param string $desc 版本描述
     * @param array $paths 路由信息
     */
    protected function addGroupInfo(string $name, string $desc, array $paths = []): void {
        if (empty($name)) {
            return;
        }

        if (!isset($this->groups[$name])) {
            $this->groups[$name] = ['name' => $name, 'description' => $desc, 'paths' => [],];
        }

        if (!empty($desc)) {
            $this->groups[$name]['description'] = $desc;
        }

        if (!empty($paths)) {
            $this->groups[$name]['paths'] = array_merge_recursive($this->groups[$name]['paths'], $paths);
        }

    }


    /**
     * 是否存在子参数.如检查数组中是否存在row的子参数,形如row.name等.
     * @param array $params
     * @param string $parentName 父参数名称
     * @return bool
     */
    public static function hasDotSubParamter(array $params, string $parentName): bool {
        /** @var \Hyperf\Apihelper\Annotation\Params $item */
        foreach ($params as $item) {
            if (strpos($item->name, '.') && strpos($item->name, $parentName) === 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * 生成参数
     * @param array $params
     * @param string $path
     * @return array
     */
    public function makeParameters(array $params, string $path): array {
        $path = self::turnPath($path);

        $parameters = [];

        /** @var \Hyperf\Apihelper\Annotation\Params $item */
        foreach ($params as $item) {
            //将如row.usr.name 转换为 row[usr][name]
            $hasDot  = strpos($item->name, '.');
            $dotName = '';
            if ($hasDot) {
                $arr = explode('.', $item->name);
                array_walk($arr, function (&$v, $i) {
                    if ($i > 0) {
                        $v = "[{$v}]";
                    }
                });
                $dotName = implode('', $arr);
            }

            $property = [
                'in'          => $item->in,
                'name'        => $hasDot ? $dotName : $item->name,
                'description' => $item->description,
                'required'    => $item->required,
                'type'        => $item->type,
            ];
            if ($item->type == 'array') {
                $property['name']             = "{$item->name}[]";
                $property['items']            = new \stdClass();
                $property['collectionFormat'] = 'multi';
            }

            //这里的对象,是键值对数组
            if ($item->type == 'object') {
                //若有下级参数,则不显示父级参数
                if (self::hasDotSubParamter($params, $item->name)) {
                    continue;
                }

                $property['name']             = "{$item->name}";
                $property['type']             = 'array';
                $property['items']            = new \stdClass();
                $property['required']         = false;
                $property['collectionFormat'] = 'multi';
            }

            $parameters[$item->name] = $property;

            if (!is_null($item->default)) {
                $parameters[$item->name]['default'] = $item->default;
            }

            if (!is_null($item->enum)) {
                $parameters[$item->name]['enum'] = $item->enum;
            }

            //字段值举例
            if (!is_null($item->example)) {
                $parameters[$item->name]['x-example'] = $item->example;
            }
        }

        return array_values($parameters);
    }


    /**
     * 生成响应
     * @param array $responses
     * @param string $path
     * @param string $method
     * @return array
     */
    public function makeResponses(array $responses, string $path, string $method): array {
        $path = self::turnPath($path);
        $resp = [];

        //基本响应体
        /** @var \Hyperf\Apihelper\Controller\ControllerInterface $baseCtrlClass */
        $baseCtrlClass = $this->confGlobal->get('apihelper.api.base_controller');
        $baseSchema    = call_user_func([$baseCtrlClass, 'getSchemaResponse']);

        /** @var ApiResponse $item */
        foreach ($responses as $item) {
            $resp[$item->code] = ['description' => $item->description,];
            $modelDefiniName   = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $item->code;
            if ($item->schema) {
                //引用已定义的模型
                if (is_array($item->schema) && array_key_exists('$ref', $item->schema) && array_key_exists($item->schema['$ref'], $this->confSwagger['definitions'])) {
                    //检查结构是否和基本响应体结构相同
                    [$schemaName, $schemaMethod] = self::extractSchemaNameMethod($item->schema['$ref']);
                    $controllerClass = $this->confSwagger['schemaMethods'][$schemaMethod] ?? '';
                    $schemaData      = call_user_func([$controllerClass, $schemaMethod]);
                    if (!ArrayHelper::compareSchema($baseSchema, $schemaData)) {
                        throw new RuntimeException("[{$schemaMethod}] must have the same structure as the return value of [getSchemaResponse]");
                    }

                    //若响应结构有指定字段引用其他已定义的模型
                    $item->refValue = self::turnRefSchema2Name(strval($item->refValue));
                    if (isset($schemaData[$item->refKey]) && !empty($item->refValue) && array_key_exists($item->refValue, $this->confSwagger['definitions'])) {
                        $schemaData[$item->refKey] = self::turnName2RefSchema($item->refValue);
                        $ret                       = $this->responseSchemaTodefinition($schemaData, $modelDefiniName);
                        if ($ret) {
                            $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelDefiniName;
                            break;
                        }
                    }

                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $item->schema['$ref'];
                } else {
                    $ret = $this->responseSchemaTodefinition($item->schema, $modelDefiniName);
                    if ($ret) {
                        $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelDefiniName;
                    }
                }
            }
        }

        return $resp;
    }


    /**
     * 响应结构体定义
     * @param $schema
     * @param $modelName
     * @param int $level
     * @return array|bool
     */
    public function responseSchemaTodefinition($schema, $modelName, $level = 0) {
        if (!$schema) {
            return false;
        }
        $definition = [];
        foreach ($schema as $key => $val) {
            $_key             = str_replace('_', '', $key);
            $property         = [];
            $property['type'] = gettype($val);
            if (is_array($val)) {
                $definitionName = $modelName . ucfirst($_key);
                if ($property['type'] == 'array' && isset($val[0])) {
                    if (is_array($val[0])) {
                        $property['type']          = 'array';
                        $ret                       = $this->responseSchemaTodefinition($val[0], $definitionName, 1);
                        $property['items']['$ref'] = '#/definitions/' . $definitionName;
                    } else {
                        $property['type']          = 'array';
                        $property['items']['type'] = gettype($val[0]);
                    }
                } else {
                    $property['type'] = 'object';
                    $ret              = $this->responseSchemaTodefinition($val, $definitionName, 1);
                    $property['$ref'] = '#/definitions/' . $definitionName;
                }
                if (isset($ret)) {
                    $this->confSwagger['definitions'][$definitionName] = $ret;
                }
            } else {
                $isRef = false;
                if (is_string($val) && ValidateHelper::startsWith($val, '$')) {
                    $val   = self::turnRefSchema2Name($val);
                    $isRef = array_key_exists($val, $this->confSwagger['definitions']);
                }

                if ($isRef) {
                    $property['type'] = 'object';
                    $property['$ref'] = '#/definitions/' . $val;
                } else {
                    $property['default'] = $val;
                }
            }
            $definition['properties'][$key] = $property;
        }

        if ($level === 0) {
            $this->confSwagger['definitions'][$modelName] = $definition;
        }

        return $definition;
    }


    /**
     * 生成json文件
     */
    public function saveJson() {
        $this->confSwagger['tags'] = array_unique(array_values($this->confSwagger['tags'] ?? []), SORT_REGULAR);
        ArrayHelper::regularSort($this->confSwagger['tags']);

        $saveDir     = DirectoryHelper::formatDir($this->confSwagger['output_dir']);
        $baseName    = $this->confSwagger['output_basename'] ?? 'swagger';
        $openSwagger = boolval($this->confSwagger['output_json']);

        if (empty($saveDir)) {
            return;
        }

        unset($this->confSwagger['output_json'], $this->confSwagger['output_dir'], $this->confSwagger['output_basename'], $this->confSwagger['schemaMethods']);

        //是否开启swagger文档功能
        if ($openSwagger) {
            $domain = UrlHelper::getDomain($this->confSwagger['host']);
            $urlArr = parse_url($this->confSwagger['host']);
            $port   = $urlArr['port'] ?? '';
            $full   = $domain . ($port ? ":" . $port : '');
            $urls   = [];

            $this->confSwagger['host'] = $full;

            $versionTags = $this->confSwagger['version_tags'] ?? [];
            unset($this->confSwagger['version_tags']);

            $swaggerAll = $this->confSwagger; //包含全部版本

            // 生成版本分组文件
            foreach ($this->groups as $group) {
                $swaggerData          = $this->confSwagger;
                $swaggerData['paths'] = array_merge_recursive(($swaggerData['paths'] ?? []), $group['paths']);
                $swaggerAll['paths']  = array_merge_recursive(($swaggerAll['paths'] ?? []), $group['paths']);

                // 当前版本的tags
                $currTags = $versionTags[$group['name']] ?? [];
                if (!empty($currTags)) {
                    ArrayHelper::regularSort($currTags);
                    $swaggerData['tags'] = $currTags;
                }

                $versionFile = "{$baseName}-{$group['name']}.json";
                array_push($urls, ['url' => "./{$versionFile}", 'name' => "{$group['name']} -- {$group['description']}"]);

                $filePath = $saveDir . $versionFile;
                file_put_contents($filePath, json_encode($swaggerData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }

            // 全部版本的接口
            $baseName .= ".json";
            array_unshift($urls, ['url' => "./{$baseName}", 'name' => "all version apis"]);
            $filePath = $saveDir . $baseName;
            file_put_contents($filePath, json_encode($swaggerAll, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // 修改index.html
            $template = $saveDir . 'template.html';
            $htmlFile = $saveDir . 'index.html';
            copy($template, $htmlFile);
            $content = file_get_contents($htmlFile);
            $urlStr  = json_encode($urls, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $urlStr  = "urls:" . str_replace("\\/", "/", $urlStr);
            $content = preg_replace("/urls:\[.*\]/is", $urlStr, $content, -1);
            file_put_contents($htmlFile, $content);
        } else {
            // 删除 *.json文件
            $fileList = DirectoryHelper::getFileTree(BASE_PATH . '/public/swagger', 'all');
            foreach ($fileList as $item) {
                $ext = FileHelper::getFileExt($item);
                if ($ext == 'json' && file_exists($item)) {
                    unlink($item);
                }
            }
        }
    }

}