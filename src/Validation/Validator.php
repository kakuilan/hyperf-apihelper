<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
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
use Kph\Helpers\ArrayHelper;
use Kph\Helpers\StringHelper;
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


    /**
     * 进行验证
     * @param array $rules
     * @param array $data
     * @param array $otherData
     * @param object|null $controller
     * @return array 结果,形如[data, errors]
     * @throws ValidationException
     */
    public function validate(array $rules, array $data, array $otherData = [], object $controller = null): array {
        $hyperfRules = $rules['hyperfs'] ?? []; //hyperf本身的验证器规则
        $customRules = $rules['customs'] ?? []; //本组件的扩展验证规则
        $allData     = array_merge($otherData, $data);
        $errors      = [];

        //先执行hyperf的验证
        $validator = $this->validator->make($allData, $hyperfRules);
        $newData   = $validator->validate();
        $data      = self::combineData($data, $newData);

        //再执行自定义验证
        foreach ($customRules as $field => $customRule) {
            if (empty($customRule)) {
                continue;
            }

            //$field字段可能存在多级,如row.name
            $fieldValue  = ArrayHelper::getDotKey($allData, $field, null);
            $detailRules = explode('|', $customRule);
            foreach ($detailRules as $detailRule) {
                $ruleName = ApiAnnotation::parseRuleName($detailRule);

                $optionStr = explode(':', $detailRule)[1] ?? '';
                $optionArr = explode(',', $optionStr);
                if ($optionStr == '' && empty($optionArr)) {
                    array_push($optionArr, '');
                }

                $convMethod = 'conver_' . $ruleName;
                if (method_exists($this, $convMethod)) {
                    $fieldValue = call_user_func_array([$this, $convMethod,], [$fieldValue, $optionArr]);
                }

                $ruleMethod = 'rule_' . $ruleName;
                if (method_exists($this, $ruleMethod)) {
                    [$check, $err] = call_user_func_array([$this, $ruleMethod,], [$fieldValue, $field, $optionArr]);
                    if (!$check) {
                        array_push($errors, $err);
                        break;
                    }
                }

                // cb_xxx,调用控制器的方法xxx
                // xxx方法,接受3个参数:$fieldValue, $field, $optionArr;
                // 返回结果是一个数组:若检查失败,为[false, 'error msg'];若检查通过,为[true, $newValue],$newValue为参数值的新值.
                $controllerMethod = str_replace(Validator::$validateCallbackPrefix, '', $ruleName);
                if (strpos($ruleName, Validator::$validateCallbackPrefix) !== false && method_exists($controller, $controllerMethod)) {
                    $chkRes = call_user_func_array([$controller, $controllerMethod,], [$fieldValue, $field, $optionArr]);

                    //检查回调结果
                    if (!is_array($chkRes) || count($chkRes) != 2 || !isset($chkRes[0]) || !is_bool($chkRes[0]) || !array_key_exists(1, $chkRes)) {
                        $msg = $this->translator->trans('apihelper.rule_callback_error_result', ['rule' => $controllerMethod]);
                        throw new ValidationException($msg);
                    }

                    [$chk, $val] = $chkRes;
                    if ($chk !== true) {
                        array_push($errors, strval($val));
                        break;
                    } elseif (!is_null($val)) {
                        $fieldValue = $val;
                    }
                }
            }

            ArrayHelper::setDotKey($data, $field, $fieldValue);
        }

        $errors = array_merge($errors, $validator->errors()->getMessages());

        return [$data, $errors];
    }


    /**
     * 转换器-默认值
     * @param $val
     * @param array $options
     * @return array|mixed
     */
    public static function conver_default($val, array $options = []) {
        if ((is_null($val) || $val == '') && !empty($options)) {
            $len = count($options);
            $val = $len == 1 ? current($options) : $options;
        }

        return $val;
    }


    /**
     * 转换器-整型
     * @param $val
     * @return int
     */
    public static function conver_int($val): int {
        return intval($val);
    }


    /**
     * 转换器-整型
     * @param $val
     * @return int
     */
    public static function conver_integer($val): int {
        return intval($val);
    }


    /**
     * 转换器-浮点
     * @param $val
     * @return float
     */
    public static function conver_float($val): float {
        return floatval($val);
    }


    /**
     * 转换器-布尔值
     * @param $val
     * @return bool
     */
    public static function conver_boolean($val): bool {
        if (empty($val) || in_array(strtolower($val), ['false', 'null', 'nil', 'none', '0',])) {
            return false;
        } elseif (in_array(strtolower($val), ['true', '1',])) {
            return true;
        }

        return boolval($val);
    }


    /**
     * 转换器-布尔值
     * @param $val
     * @return bool
     */
    public function conver_bool($val): bool {
        return self::conver_boolean($val);
    }


    /**
     * 转换器-去掉空格
     * @param $val
     * @return string
     */
    public function conver_trim($val): string {
        return StringHelper::trim(strval($val));
    }


    /**
     * 验证-枚举
     * @param $val
     * @param string $field
     * @param array $options
     * @return array [bool, err]
     */
    public function rule_enum($val, string $field, array $options = []): array {
        $err = '';
        if (empty($options)) {
            return [true, $err];
        }

        $chk = $val !== '' && in_array($val, $options);
        if (!$chk) {
            $err = $this->translator->trans('apihelper.rule_enum', ['field' => $field, 'values' => implode(',', $options)]);
        }

        return [boolval($chk), $err];
    }


    /**
     * 验证-对象(键值对数组)
     * @param $val
     * @param string $field
     * @param array $options
     * @return array [bool, err]
     */
    public function rule_object($val, string $field, array $options = []): array {
        $err = '';

        // 必须是数组
        if (!is_array($val)) {
            $err = $this->translator->trans('apihelper.rule_object', ['field' => $field]);
            return [false, $err];
        }

        // 键值不能是数字
        $keys = array_keys($val);
        foreach ($keys as $key) {
            if (is_integer($key)) {
                $err = $this->translator->trans('apihelper.rule_object', ['field' => $field]);
                return [false, $err];
            }
        }

        return [true, $err];
    }


    /**
     * 验证-自然数
     * @param $val
     * @param string $field
     * @param array $options
     * @return array [bool, err]
     */
    public function rule_natural($val, string $field, array $options = []): array {
        $err = '';
        $chk = ValidateHelper::isNaturalNum($val);
        if (!$chk) {
            $err = $this->translator->trans('apihelper.rule_natural', ['field' => $field]);
        }

        return [boolval($chk), $err];
    }


    /**
     * 验证-中国手机号
     * @param $val
     * @param string $field
     * @param array $options
     * @return array [bool, err]
     */
    public function rule_cnmobile($val, string $field, array $options = []): array {
        $err = '';
        $chk = ValidateHelper::isMobilecn($val);
        if (!$chk) {
            $err = $this->translator->trans('apihelper.rule_cnmobile', ['field' => $field]);
        }

        return [boolval($chk), $err];
    }


    /**
     * 验证-中国身份证号
     * @param $val
     * @param string $field
     * @param array $options
     * @return array [bool, err]
     */
    public function rule_cncreditno($val, string $field, array $options = []): array {
        $err = '';
        $chk = ValidateHelper::isChinaCreditNo($val);
        if (!$chk) {
            $err = $this->translator->trans('apihelper.rule_cncreditno', ['field' => $field]);
        }

        return [boolval($chk), $err];
    }


    /**
     * 验证-安全密码
     * @param $val
     * @param string $field
     * @param array $options
     * @return array [bool, err]
     */
    public function rule_safe_password($val, string $field, array $options = []): array {
        $err   = '';
        $level = StringHelper::passwdSafeGrade($val);
        if ($level < 2) {
            $err = $this->translator->trans('apihelper.rule_safe_password_simple', ['field' => $field]);
            return [false, $err];
        }

        return [true, $err];
    }


}