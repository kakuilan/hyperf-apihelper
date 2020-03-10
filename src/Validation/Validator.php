<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/9
 * Time: 16:02
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Validation;

use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Apihelper\Exception\ValidationException;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Server\Exception\RuntimeException;
use Hyperf\Utils\Arr;
use Hyperf\Validation\Concerns\ValidatesAttributes;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Kph\Helpers\ValidateHelper;


/**
 * Class Validator
 * @package Hyperf\Apihelper\Validation
 */
class Validator implements ValidationInterface {

    /**
     * 验证回调方法规则前缀(供自定义控制器的验证方法使用),前缀之后就是具体的控制器方法名称.如:
     * 规则'cb_checkName',即为要调用控制器的方法checkName去做验证.
     * @var string
     */
    public static $validateCallbackPrefix = 'cb_';


    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    public $validator;


    /**
     * @Inject
     * @var TranslatorInterface
     */
    private $translator;


    /**
     * 错误消息
     * @var array
     */
    public $errors = [];


    /**
     * 合并数据,将新数据更新到源数据中.
     * @param array $origin 源数据
     * @param array $new 新数据
     * @return array
     */
    public static function combineData(array $origin, array $new = []): array {
        if (empty($origin) || empty($new)) {
            return [];
        }

        foreach ($origin as $k => $item) {
            if (isset($new[$k])) {
                $origin[$k] = $new[$k];
            }
        }

        return $origin;
    }


    /**
     * 重新排序(某字段的)详细规则数组(类型检查放在前面)
     * @param array $rules
     * @return array
     */
    public static function sortDetailRules(array $rules): array {
        $priorities = ['default', 'int', 'integer', 'bool', 'boolean', 'number', 'numeric', 'float', 'string', 'array', 'object'];
        $res        = [];

        foreach ($rules as $rule) {
            $lowRule = strtolower($rule);
            if (in_array($lowRule, $priorities)) {
                if ($lowRule == 'int') {
                    $rule = 'integer';
                } elseif ($lowRule == 'bool') {
                    $rule = 'boolean';
                } elseif ($lowRule == 'number') {
                    $rule = 'numeric';
                }

                array_unshift($res, $rule);
            } else {
                array_push($res, $rule);
            }
        }

        return $res;
    }


    public function validate(array $rules, array $data, array $otherData = [], object $controller = null): array {
        $hyperfRules = []; //hyperf本身的验证器规则
        $customRules = []; //本组件的扩展验证规则
        $allData     = array_merge($otherData, $data);

        foreach ($rules as $field => $rule) {
            $detailRules = self::sortDetailRules(explode('|', $rule));
            $arr1        = $arr2 = [];

            foreach ($detailRules as $detailRule) {
                $ruleName = ApiAnnotation::parseRuleName($detailRule);

                //是否本组件的转换器
                $convMethod = 'conver_' . $ruleName;
                if (method_exists($this, $convMethod)) {
                    array_push($arr1, $detailRule);
                }

                //是否本组件的验证规则
                $ruleMethod = 'rule_' . $ruleName;
                if (method_exists($this, $ruleMethod)) {
                    array_push($arr1, $detailRule);
                }

                // cb_xxx,调用控制器的方法xxx
                $controllerMethod = str_replace(self::$validateCallbackPrefix, '', $ruleName);
                if (strpos($ruleName, self::$validateCallbackPrefix) !== false && method_exists($controller, $controllerMethod)) {
                    array_push($arr1, $detailRule);
                    continue;
                }


            }

        }


        return [];
    }


    /**
     * 获取错误信息
     * @return array
     */
    public function getError(): array {
        return $this->errors;
    }

}