<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/10
 * Time: 09:40
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Swagger;

use Doctrine\Common\Annotations\AnnotationException;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\Methods;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Params;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\ApplicationContext;
use Kph\Helpers\ArrayHelper;
use Kph\Helpers\DirectoryHelper;
use Kph\Helpers\FileHelper;
use Kph\Helpers\UrlHelper;
use Kph\Helpers\ValidateHelper;
use RuntimeException;


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
    public $groups;


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
        $response = [
            'type'       => 'object',
            'required'   => [],
            'properties' => [
                'status' => ['type' => 'boolean', 'example' => true,],
                'msg'    => ['type' => 'string', 'example' => 'success',],
                'code'   => ['type' => 'integer', 'format' => 'int64', 'example' => 200,],
                'data'   => ['type' => 'array', 'example' => [],],],
        ];

        $this->confSwagger['definitions']['Response']    = $response;
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
     * 添加接口路径信息
     * @param string $className
     * @param string $methodName
     * @param string $path
     * @throws AnnotationException
     */
    public function addPath(string $className, string $methodName, string $path): void {
        //获取类文件的注解信息
        $classAnnotation   = ApiAnnotation::getClassMetadata($className);
        $methodAnnotations = ApiAnnotation::getMethodMetadata($className, $methodName);
        $versionAnnotation = ApiAnnotation::getVersionMetadata($className);

        $params    = [];
        $paths     = [];
        $responses = [];

        $hasVersion = is_object($versionAnnotation) && !empty($versionAnnotation->group) && is_string($versionAnnotation->group);
        //检查版本号是否合法
        if ($hasVersion) {
            if (!preg_match("/^(?!_)[a-zA-Z0-9_]+$/u", $versionAnnotation->group)) {
                throw new RuntimeException('Version group name can only be in english, numerals, and underscores');
            }
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
        $tag                             = $classAnnotation->tag ?: $className;
        $this->confSwagger['tags'][$tag] = ['name' => $tag, 'description' => $classAnnotation->description,];

        $method                = strtolower($reqMethod->methods[0]);
        $paths[$path][$method] = [
            'tags'        => [$tag,],
            'summary'     => $reqMethod->summary,
            'parameters'  => $this->makeParameters($params, $path), //接口默认接收的MIME类型
            'consumes'    => ['application/x-www-form-urlencoded', 'application/json', 'multipart/form-data',], //接口默认的响应类型
            'produces'    => ['application/json',],
            'responses'   => $this->makeResponses($responses, $path, $method),
            'description' => $reqMethod->description,
        ];

        if ($hasVersion) {
            $this->addGroupInfo($versionAnnotation->group, $versionAnnotation->description, $paths);
        } else {
            $this->confSwagger['paths'] = array_merge(($this->confSwagger['paths'] ?? []), $paths);
        }
    }


    /**
     * 添加版本分组信息
     * @param string $name
     * @param string $desc
     * @param array $paths
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
            $this->groups[$name]['paths'] = array_merge($this->groups[$name]['paths'], $paths);
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

        /** @var ApiResponse $item */
        foreach ($responses as $item) {
            $resp[$item->code] = ['description' => $item->description,];
            if ($item->schema) {
                //引用已定义的模型
                if (is_array($item->schema) && array_key_exists('$ref', $item->schema) && array_key_exists($item->schema['$ref'], $this->confSwagger['definitions'])) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $item->schema['$ref'];
                } else {
                    $modelName = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $item->code;
                    $ret       = $this->responseSchemaTodefinition($item->schema, $modelName);
                    if ($ret) {
                        $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelName;
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
                $property['default'] = $val;
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

        $saveDir     = DirectoryHelper::formatDir($this->confSwagger['output_dir']);
        $baseName    = $this->confSwagger['output_basename'] ?? 'swagger';
        $openSwagger = boolval($this->confSwagger['output_json']);

        $swaggerDir = str_replace(DirectoryHelper::formatDir(BASE_PATH . '/public'), '', $saveDir);
        $swaggerDir = rtrim($swaggerDir, '/');

        if (empty($saveDir)) {
            return;
        }

        unset($this->confSwagger['output_json'], $this->confSwagger['output_dir'], $this->confSwagger['output_basename']);

        //是否开启swagger文档功能
        if ($openSwagger) {
            $http    = $this->confSwagger['schemes'][0] ?? 'http';
            $siteUrl = UrlHelper::formatUrl(strtolower("{$http}://" . $this->confSwagger['host']));
            $urls    = [];

            $swaggerAll = $this->confSwagger; //包含全部版本

            // 生成版本分组文件
            foreach ($this->groups as $group) {
                $swaggerData          = $this->confSwagger;
                $swaggerData['paths'] = array_merge(($swaggerData['paths'] ?? []), $group['paths']);
                $swaggerAll['paths']  = array_merge(($swaggerAll['paths'] ?? []), $group['paths']);

                $versionFile = "{$baseName}-{$group['name']}.json";
                array_push($urls, ['url' => "{$siteUrl}/{$swaggerDir}/{$versionFile}", 'name' => "{$group['name']} -- {$group['description']}"]);

                $filePath = $saveDir . $versionFile;
                file_put_contents($filePath, json_encode($swaggerData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }

            // 全部版本的接口
            $baseName .= ".json";
            array_unshift($urls, ['url' => "{$siteUrl}/{$swaggerDir}/{$baseName}", 'name' => "all version apis"]);
            $filePath = $saveDir . $baseName;
            file_put_contents($filePath, json_encode($swaggerAll, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // 修改index.html
            $htmlFile = $saveDir . 'index.html';
            $content  = file_get_contents($htmlFile);
            $urlStr   = json_encode($urls, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $urlStr   = "urls:" . str_replace("\\/", "/", $urlStr);
            $content  = preg_replace("/urls:\[.*\]/is", $urlStr, $content, -1);
            file_put_contents($htmlFile, $content);
        } else {
            // 删除 *.json文件
            $fileList = DirectoryHelper::getFileTree(BASE_PATH . '/public/swagger/', 'file');
            foreach ($fileList as $item) {
                $ext = FileHelper::getFileExt($item);
                if ($ext == 'json') {
                    @unlink($item);
                }
            }
        }
    }

}