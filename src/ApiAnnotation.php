<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 18:53
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationException;
use Hyperf\Apihelper\Annotation\ApiController;
use Hyperf\Apihelper\Annotation\ApiVersion;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\ReflectionManager;
use Lkk\Helpers\ValidateHelper;

class ApiAnnotation {


    /**
     * 获取类的元数据
     * @param $className
     * @return array|\ArrayAccess|mixed|null
     */
    public static function classMetadata($className) {
        return AnnotationCollector::getClassAnnotation($className, ApiController::class);
    }


    /**
     * 获取版本号的元数据
     * @param $className
     * @return array|\ArrayAccess|mixed|null
     */
    public static function versionMetadata($className) {
        return AnnotationCollector::getClassAnnotation($className, ApiVersion::class);
    }


    /**
     * 获取方法的元数据
     * @param $className
     * @param $methodName
     * @return array
     * @throws AnnotationException
     */
    public static function methodMetadata($className, $methodName) {
        $reflectMethod     = ReflectionManager::reflectMethod($className, $methodName);
        $reader            = new AnnotationReader();
        $methodAnnotations = $reader->getMethodAnnotations($reflectMethod);
        return $methodAnnotations;
    }


    /**
     * 将类名(包括命名空间)转换为基础路径
     * @param $className
     * @return mixed|string
     */
    public static function basePath($className) {
        $path = strtolower($className);
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
    public static function parseDetailsByRule(string $rule):array {
        $arr = explode('|', $rule);
        array_walk($arr, function(&$item) {
            $item = trim($item);
            if(ValidateHelper::isWord($item)) {
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
        //过滤如gt[0] 或 enum[0,1]
        $res = preg_replace('/\[.*\]/', '', $str);

        if (strpos($res, ':')) { //形如 max:value
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


}