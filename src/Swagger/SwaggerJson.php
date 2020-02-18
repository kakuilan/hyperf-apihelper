<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 18:47
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Swagger;

use Lkk\Helpers\ArrayHelper;
use Lkk\Helpers\DirectoryHelper;
use Lkk\Helpers\FileHelper;
use Lkk\Helpers\UrlHelper;
use Lkk\Helpers\ValidateHelper;
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Params;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\Utils\ApplicationContext;
use Doctrine\Common\Annotations\AnnotationException;
use RuntimeException;

class SwaggerJson {


    /**
     * 全局配置
     * @var ConfigInterface|mixed
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


    public function __construct() {
        $this->confGlobal  = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $this->confSwagger = $this->confGlobal->get('apihelper.swagger');
    }


    /**
     * 添加接口路径信息
     * @param $className
     * @param $methodName
     * @throws AnnotationException
     */
    public function addPath($className, $methodName) {
        //获取类文件的注解信息
        $classAnnotation    = ApiAnnotation::classMetadata($className);
        $methodAnnotations  = ApiAnnotation::methodMetadata($className, $methodName);
        $versionAnnotations = ApiAnnotation::versionMetadata($className);

        $params    = [];
        $responses = [];
        $paths     = [];

        $hasVersion = is_object($versionAnnotations) && !empty($versionAnnotations->group) && is_string($versionAnnotations->group);
        //检查版本号是否合法
        if ($hasVersion) {
            if (!preg_match("/^(?!_)[a-zA-Z0-9_]+$/u", $versionAnnotations->group)) {
                throw new RuntimeException('Version group name can only be in english, numerals, and underscores');
            }
        }

        /** @var \Hyperf\Apihelper\Annotation\Methods $mapping */
        $mapping = null;

        foreach ($methodAnnotations as $option) {
            if ($option instanceof Mapping) {
                $mapping = $option;
            }

            if ($option instanceof Params) {
                $params[] = $option;
            }

            if ($option instanceof ApiResponse) {
                $responses[] = $option;
            }
        }

        $tag                             = $classAnnotation->tag ?: $className;
        $this->confSwagger['tags'][$tag] = ['name' => $tag, 'description' => $classAnnotation->description,];

        $basePath = ApiAnnotation::basePath($className);
        $path     = $basePath . '/' . $methodName;
        //若注解中有路径信息
        if ($mapping->path) {
            //仅仅是路由参数,如 {id}
            $justId = preg_match('/^{.*}$/', $mapping->path);
            if ($justId) {
                $path = $basePath . '/' . $mapping->path;
            } else {
                $path = $mapping->path;
            }
        }
        $method                = strtolower($mapping->methods[0]);
        $paths[$path][$method] = [
            'tags'     => [$tag,],
            'summary' => $mapping->summary,
            'parameters' => $this->makeParameters($params, $path), //接口默认接收的MIME类型
            'consumes' => ['application/x-www-form-urlencoded', 'application/json', 'multipart/form-data',], //接口默认的回复类型
            'produces' => ['application/json',],
            'responses' => $this->makeResponses($responses, $path, $method),
            'description' => $mapping->description,
        ];

        if ($hasVersion) {
            $this->addGroupInfo($versionAnnotations->group, $versionAnnotations->description, $paths);
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
    protected function addGroupInfo(string $name, string $desc, array $paths = []) {
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
     * 初始化常用模型定义
     */
    public function initDefinitions() {
        $response = [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'status' => ['type' => 'boolean', 'example' => true,],
                'msg' => ['type' => 'string', 'example' => 'success',],
                'code' => ['type' => 'integer', 'format' => 'int64', 'example' => 200,],
                'data' => ['type' => 'array', 'example' => [],],],
        ];

        $arraySchema  = ['type' => 'array', 'required' => [], 'items' => ['type' => 'string'],];
        $objectSchema = ['type' => 'object', 'required' => [], 'items' => ['type' => 'string'],];

        $this->confSwagger['definitions']['Response']    = $response;
        $this->confSwagger['definitions']['ModelArray']  = $arraySchema;
        $this->confSwagger['definitions']['ModelObject'] = $objectSchema;
    }


    /**
     * 将规则转换为结构
     * @param array $rules
     * @return array
     */
    public function rules2schema(array $rules): array {
        $schema = ['type' => 'object', 'required' => [], 'properties' => [],];

        foreach ($rules as $field => $rule) {
            $property  = [];
            $fileInfo  = explode('|', $field);
            $fieldName = $fileInfo[0];
            if (!is_array($rule)) {
                $type = $this->getTypeByRule($rule);
            } else {
                //TODO 结构体多层
                $type = 'string';
            }

            if ($type == 'array') {
                $property['$ref'] = '#/definitions/ModelArray';;
            } elseif ($type == 'object') {
                $property['$ref'] = '#/definitions/ModelObject';;
            }

            $property['type']                 = $type;
            $property['description']          = $fileInfo[1] ?? '';
            $schema['properties'][$fieldName] = $property;
        }

        return $schema;
    }


    /**
     * 从规则中获取字段类型
     * @param string $rule
     * @return string
     */
    public function getTypeByRule(string $rule) {
        $details = ApiAnnotation::parseDetailsByRule($rule);
        $digItem = ArrayHelper::dstrpos($rule, ['gt', 'gte', 'lt', 'lte', 'max', 'min'], true);

        if (array_intersect($details, ['integer', 'int'])) {
            return 'integer';
        } elseif (array_intersect($details, ['float'])) {
            return 'float';
        } elseif (array_intersect($details, ['number','numeric'])) {
            return 'number';
        } elseif (array_intersect($details, ['boolean', 'bool'])) {
            return 'boolean';
        } elseif (array_intersect($details, ['array'])) {
            return 'array';
        } elseif (array_intersect($details, ['object'])) {
            return 'object';
        } elseif ($digItem) {
            foreach ($details as $detail) {
                if (strpos($detail, ':') && stripos($detail, $digItem) !== false) {
                    //是否有规则选项,如 between:1,20 中的 :1,20
                    preg_match('/:(.*)/', $detail, $match);
                    $options = $match[1] ?? '';
                    $arr     = explode(',', $options);
                    $first   = $arr[0] ?? '';

                    if (ValidateHelper::isFloat($first)) {
                        return 'float';
                    } elseif (ValidateHelper::isInteger($first)) {
                        return 'integer';
                    }
                }
            }
        }

        return 'string';
    }


    /**
     * 生成参数
     * @param array $params
     * @param string $path
     * @return array
     */
    public function makeParameters(array $params, string $path): array {
        $this->initDefinitions();

        $path = self::turnPath($path);

        $parameters = [];

        /** @var \Hyperf\Apihelper\Annotation\Params $item */
        foreach ($params as $item) {
            $property = [
                'in' => $item->in,
                'name' => $item->name,
                'description' => $item->description,
                'required' => $item->required,
                'type' => $item->type,
            ];
            if($item->type =='array') {
                $property['name'] = "{$item->name}[]";
                $property['items'] = new \stdClass();
                $property['collectionFormat'] = 'multi';
            }
            //这里的对象,是键值对数组
            if($item->type =='object') {
                $property['name'] = "{$item->name}[]";
                $property['type'] = 'array';
                $property['items'] = new \stdClass();
                $property['collectionFormat'] = 'multi';
            }

            $parameters[$item->name] = $property;

            if(!is_null($item->default)) $parameters[$item->name]['default']= $item->default;
            if(!is_null($item->enum)) $parameters[$item->name]['enum']= $item->enum;


            //单独处理body参数
            if ($item instanceof Body) {
                // TODO
//                $modelName = implode('', array_map('ucfirst', explode('/', $path)));
//
//                $schema = $this->rules2schema($item->rule);
//
//                $this->confSwagger['definitions'][$modelName] = $schema;
//                $parameters[$item->name]['schema']['$ref']    = '#/definitions/' . $modelName;
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
    public function makeResponses(array $responses, string $path, string $method) {
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
     * 转换路径中的路由参数,形如{x}
     * @param string $path
     * @return string
     */
    public static function turnPath(string $path): string {
        $path = str_replace(['{', '}'], '', $path);
        return $path;
    }


    /**
     * 生成json文件
     */
    public function save() {
        $this->confSwagger['tags'] = array_unique(array_values($this->confSwagger['tags'] ?? []), SORT_REGULAR);

        $saveDir     = DirectoryHelper::formatDir($this->confSwagger['output_dir']);
        $baseName    = $this->confSwagger['output_basename'] ?? 'swagger';
        $openSwagger = boolval($this->confSwagger['output_json']);

        $swaggerDir = str_replace(DirectoryHelper::formatDir(BASE_PATH . '/public'), '', $saveDir);
        $swaggerDir = rtrim($swaggerDir, '/');

        if (empty($saveDir))
            return;
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