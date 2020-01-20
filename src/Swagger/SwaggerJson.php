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
use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\Apihelper\Annotation\Param\Body;
use Hyperf\Apihelper\Annotation\Param\Path;
use Hyperf\Apihelper\Annotation\Params;
use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\Utils\ApplicationContext;
use Lkk\Helpers\ValidateHelper;


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


    public function __construct() {
        $this->confGlobal = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $this->confSwagger = $this->confGlobal->get('swagger');
    }



    public function addPath($className, $methodName) {
        //获取类文件的注解信息
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);
        $params = [];
        $responses = [];

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

        $tag = $classAnnotation->tag ?: $className;
        $this->confSwagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $classAnnotation->description,
        ];

        $basePath = ApiAnnotation::basePath($className);
        $path = $basePath . '/' . $methodName;
        //若注解中有路径信息
        if ($mapping->path) {
            // 仅是路由参数,如 {id}
            $justId = preg_match('/{.*}/', $mapping->path);
            if ($justId) {
                $path = $basePath . '/' . $mapping->path;
            } else {
                $path = $mapping->path;
            }
        }
        $method = strtolower($mapping->methods[0]);
        $this->confSwagger['paths'][$path][$method] = [
            'tags' => [
                $tag,
            ],
            'summary' => $mapping->summary,
            'parameters' => $this->makeParameters($params, $path),
            //接口默认接收的MIME类型
            'consumes' => [
                'application/x-www-form-urlencoded',
                'application/json',
                'multipart/form-data',
            ],
            //接口默认的回复类型
            'produces' => [
                'application/json',
            ],
            'responses' => $this->makeResponses($responses, $path, $method),
            'description' => $mapping->description,
        ];
    }


    /**
     * 初始化常用模型定义
     */
    public function initDefinitions() {
        $response = [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'status' => [
                    'type' => 'boolean',
                    'example' => true,
                ],
                'msg' => [
                    'type' => 'string',
                    'example' => 'success',
                ],
                'code' => [
                    'type' => 'integer',
                    'format' => 'int64',
                    'example' => 200,
                ],
                'data' => [
                    'type' => 'array',
                    'example' => [],
                ],
            ],
        ];

        $arraySchema = [
            'type' => 'array',
            'required' => [],
            'items' => [
                'type' => 'string'
            ],
        ];
        $objectSchema = [
            'type' => 'object',
            'required' => [],
            'items' => [
                'type' => 'string'
            ],
        ];

        $this->confSwagger['definitions']['Response'] = $response;
        $this->confSwagger['definitions']['ModelArray'] = $arraySchema;
        $this->confSwagger['definitions']['ModelObject'] = $objectSchema;
    }


    /**
     * 将规则转换为结构
     * @param array $rules
     * @return array
     */
    public function rules2schema(array $rules):array {
        $schema = [
            'type' => 'object',
            'required' => [],
            'properties' => [],
        ];

        foreach ($rules as $field => $rule) {
            $property = [];
            $fileInfo = explode('|', $field);
            $fieldName = $fileInfo[0];
            if (!is_array($rule)) {
                $type = $this->getTypeByRule($rule);
            } else {
                //TODO 结构体多层
                $type = 'string';
            }

            if ($type == 'array') {
                $property['$ref'] = '#/definitions/ModelArray';;
            }elseif ($type == 'object') {
                $property['$ref'] = '#/definitions/ModelObject';;
            }

            $property['type'] = $type;
            $property['description'] = $fileInfo[1] ?? '';
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
        $details = explode('|', $rule);
        $digItem = ArrayHelper::dstrpos($rule, ['gt','gte','lt','lte','max','min','numeric'], true);

        if (array_intersect($details, ['integer', 'int'])) {
            return 'integer';
        }elseif (array_intersect($details, ['float'])) {
            return 'float';
        }elseif (array_intersect($details, ['boolean', 'bool'])) {
            return 'boolean';
        }elseif (array_intersect($details, ['array'])) {
            return 'array';
        }elseif (array_intersect($details, ['object'])) {
            return 'object';
        }elseif ($digItem) {
            foreach ($details as $detail) {
                if(strpos($detail, ':') && stripos($detail, $digItem) !==false) {
                    //是否有规则选项,如 between:1,20 中的 :1,20
                    preg_match('/:(.*)/', $detail, $match);
                    $options = $match[1] ?? '';
                    $arr = explode(',', $options);
                    $first = $arr[0] ?? '';

                    if (ValidateHelper::isFloat($first)) {
                        return 'float';
                    }elseif (ValidateHelper::isInteger($first)) {
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
    public function makeParameters(array $params, string $path):array {
        $this->initDefinitions();

        $path = self::turnPath($path);

        $parameters = [];

        /** @var \Hyperf\Apihelper\Annotation\Params $item */
        foreach ($params as $item) {
            $parameters[$item->name] = [
                'in' => $item->in,
                'name' => $item->name,
                'description' => $item->description,
                'required' => $item->required,
                'type' => $item->type,
            ];

            //单独处理body参数
            if ($item instanceof Body) {
                $modelName = implode('', array_map('ucfirst', explode('/', $path)));

                $schema = $this->rules2schema($item->rules);

                $this->confSwagger['definitions'][$modelName] = $schema;
                $parameters[$item->name]['schema']['$ref'] = '#/definitions/' . $modelName;
            }

            //TODO 处理 path 参数
            if ($item instanceof Path) {

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
            $resp[$item->code] = [
                'description' => $item->description,
            ];
            if ($item->schema) {
                //引用已定义的模型
                if(is_array($item->schema) && array_key_exists('$ref', $item->schema) && array_key_exists($item->schema['$ref'], $this->confSwagger['definitions'])) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $item->schema['$ref'];
                }else{
                    $modelName = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) .'Response' . $item->code;
                    $ret = $this->responseSchemaTodefinition($item->schema, $modelName);
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
            $_key = str_replace('_', '', $key);
            $property = [];
            $property['type'] = gettype($val);
            if (is_array($val)) {
                $definitionName = $modelName . ucfirst($_key);
                if ($property['type'] == 'array' && isset($val[0])) {
                    if (is_array($val[0])) {
                        $property['type'] = 'array';
                        $ret = $this->responseSchemaTodefinition($val[0], $definitionName, 1);
                        $property['items']['$ref'] = '#/definitions/' . $definitionName;
                    } else {
                        $property['type'] = 'array';
                        $property['items']['type'] = gettype($val[0]);
                    }
                } else {
                    $property['type'] = 'object';
                    $ret = $this->responseSchemaTodefinition($val, $definitionName, 1);
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
    public static function turnPath(string $path):string {
        $path = str_replace(['{', '}'], '', $path);
        return $path;
    }


    /**
     * 生成json文件
     */
    public function save() {
        $this->confSwagger['tags'] = array_values($this->confSwagger['tags'] ?? []);
        $outputFile = $this->confSwagger['output_file'] ?? '';
        if (!$outputFile) {
            return;
        }
        unset($this->confSwagger['output_file']);

        file_put_contents($outputFile, json_encode($this->confSwagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }



}