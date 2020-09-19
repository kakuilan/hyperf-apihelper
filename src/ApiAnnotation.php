<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/6
 * Time: 16:34
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper;

use ArrayAccess;
use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Hyperf\Apihelper\Annotation\ApiController;
use Hyperf\Apihelper\Annotation\ApiVersion;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use Kph\Helpers\StringHelper;
use Kph\Helpers\ValidateHelper;


/**
 * Class ApiAnnotation
 * @package Hyperf\Apihelper
 */
class ApiAnnotation {


    /**
     * 响应结构模型方法名前缀
     * @var string
     */
    public static $schemaMethodPrefix = 'getSchema';


    /**
     * 路由规则缓存,形如
     * //    $arr = [
     * //        'class::method' => [
     * //            'hyperfs' => [
     * //                'field1' => [
     * //                    'detailRule1',
     * //                    'detailRule2',
     * //                ],
     * //                'field2' => [],
     * //            ],
     * //            'customs' => [],
     * //        ],
     * //    ];
     * @var object
     */
    private $routeCache = null;


    /**
     * 获取类的元数据
     * @param string $className
     * @return array|ArrayAccess|mixed|null
     */
    public static function getClassMetadata(string $className) {
        return AnnotationCollector::getClassAnnotation($className, ApiController::class);
    }


    /**
     * 获取版本号的元数据
     * @param string $className
     * @return array|ArrayAccess|mixed|null
     */
    public static function getVersionMetadata(string $className) {
        return AnnotationCollector::getClassAnnotation($className, ApiVersion::class);
    }


    /**
     * 获取方法的元数据
     * @param string $className
     * @param string $methodName
     * @return array
     * @throws AnnotationException
     */
    public static function getMethodMetadata(string $className, string $methodName) {
        $reflectMethod     = ReflectionManager::reflectMethod($className, $methodName);
        $reader            = new AnnotationReader();
        $methodAnnotations = $reader->getMethodAnnotations($reflectMethod);
        return $methodAnnotations;
    }


    /**
     * 将控制器类名(包括命名空间)转换为URL路径
     * @param string $controllerClassName
     * @return string
     */
    public static function controller2UrlPath(string $controllerClassName): string {
        $path = strtolower($controllerClassName);
        $path = str_replace('\\', '/', $path);
        $path = str_replace('app/controller', '', $path); //去掉命名空间的前缀,如 app/controller/indexcontroller=>indexcontroller
        $path = str_replace('controller', '', $path); //去掉类名中带有的controller,如indexcontroller=>index
        return $path;
    }


    /**
     * 从规则字符串中解析出具体的规则数组
     * @param string $rule
     * @return array
     */
    public static function parseDetailsByRule(string $rule): array {
        $arr = explode('|', $rule);
        array_walk($arr, function (&$item) {
            $item = trim($item);
            if (ValidateHelper::isWord($item)) {
                $item = strtolower($item);
            }

            return $item;
        });

        return array_unique(array_filter($arr));
    }


    /**
     * 解析规则名
     * @param string $str
     * @return string
     */
    public static function parseRuleName(string $str): string {
        //将如gt[0] 或 enum[0,1] 转换为gt, enum
        $res = preg_replace('/\[.*\]/', '', $str);

        //形如 max:value
        if (strpos($res, ':')) {
            $arr = explode(':', $res);
            $res = $arr[0];
        }

        return trim($res);
    }


    /**
     * 从注解key中获取字段名
     * @param string $key
     * @return string
     */
    public static function getFieldByKey(string $key): string {
        $arr = explode('|', $key);
        $res = $arr[0] ?? '';
        return $res;
    }


    /**
     * 从规则中获取字段类型
     * @param string $rule
     * @return string
     */
    public static function getTypeByRule(string $rule): string {
        $details   = self::parseDetailsByRule($rule);
        $digitItem = StringHelper::dstrpos($rule, ['gt', 'gte', 'lt', 'lte', 'max', 'min','between'], true);

        if (array_intersect($details, ['integer', 'int'])) {
            return 'integer';
        } elseif (array_intersect($details, ['float'])) {
            return 'float';
        } elseif (array_intersect($details, ['number', 'numeric'])) {
            return 'number';
        } elseif (array_intersect($details, ['boolean', 'bool'])) {
            return 'boolean';
        } elseif (array_intersect($details, ['array'])) {
            return 'array';
        } elseif (array_intersect($details, ['object'])) {
            return 'object';
        } elseif (array_intersect($details, ['file', 'image'])) {
            return 'file';
        } elseif (array_intersect($details, ['string','trim'])) {
            return 'string';
        } elseif ($digitItem) {
            foreach ($details as $detail) {
                if (strpos($detail, ':') && stripos($detail, $digitItem) !== false) {
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
     * 根据值获取类型
     * @param $value
     * @return string
     */
    public static function getTypeByValue($value): string {
        if (ValidateHelper::isInteger($value)) {
            return 'integer';
        } elseif (ValidateHelper::isFloat($value)) {
            return 'float';
        } elseif (is_numeric($value)) {
            return 'number';
        } elseif (is_bool($value)) {
            return 'boolean';
        } elseif (is_array($value)) {
            return ValidateHelper::isAssocArray($value) ? 'object' : 'array';
        } elseif (is_object($value)) {
            return 'object';
        }

        return 'string';
    }


    /**
     * 设置路由缓存
     * @param object $cache
     */
    public function setRouteCache(object $cache): void {
        if (!is_null($cache)) {
            $this->routeCache = $cache;
        }
    }


    /**
     * 获取路由缓存
     * @return object
     */
    public function getRouteCache(): object {
        return $this->routeCache;
    }


}