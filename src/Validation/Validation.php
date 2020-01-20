<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/16
 * Time: 15:47
 * Desc:
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Validation;

use Lkk\Helpers\StringHelper;
use Lkk\Helpers\ValidateHelper;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Apihelper\Exception\ValidationException;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use Hyperf\Validation\Concerns\ValidatesAttributes;

class Validation implements ValidationInterface {

    /**
     * @Inject()
     * @var \Hyperf\Validation\Contract\ValidatorFactoryInterface
     */
    public $validator;


    /**
     * @Inject
     * @var \Hyperf\Contract\TranslatorInterface
     */
    private $translator;


    /**
     * 错误消息
     * @var array
     */
    public $errors = [];


    /**
     * 数据
     * @var array
     */
    public $data = [];


    /**
     * 执行检查
     * @param array $rules 规则数组
     * @param array $data 数据
     * @param object $obj 控制器对象
     * @param string $keyTree
     * @return array|bool
     * @throws ValidationException
     */
    public function check(array $rules, array $data, object $obj = null, string $keyTree = null) {
        $this->errors = [];
        $this->data = [];

        $realRules = []; //hyperf本身的验证器规则
        $customRules = []; //本组件的扩展验证规则
        $finalData = $data;

        foreach ($rules as $field => $rule) {
            $fileInfo = explode('|', $field);
            $fieldName = $fileInfo[0];
            $tree = $keyTree ? $keyTree . '.' . $fieldName : $fieldName;

            //嵌套规则数组
            if (is_array($rule)) {
                $ret = $this->check($rule, Arr::get($finalData, $fieldName, []), $obj, $tree);
                if ($ret === false) {
                    return false;
                }
                $finalData[$field] = $ret;
                continue;
            }

            $detailRules = explode('|', $rule);
            $arr1 = $arr2 = [];
            foreach ($detailRules as $detailRule) {
                $ruleName = self::parseRuleName($detailRule);

                //是否本组件的转换器
                $convMethod = 'conver_'. $ruleName;
                if (method_exists($this, $convMethod)) {
                    array_push($arr1, $detailRule);
                }

                //是否本组件的验证规则
                $ruleMethod = 'rule_'. $ruleName;
                if (method_exists($this, $ruleMethod)) {
                    array_push($arr1, $detailRule);
                }

                // cb_xxx,调用控制器的方法xxx
                $controllerMethod = str_replace('cb_', '', $ruleName);
                if (strpos($ruleName, 'cb_') !== false && method_exists($obj, $controllerMethod)) {
                    array_push($arr1, $detailRule);
                    continue;
                }

                //是否hyperf验证规则
                $hyperfMethod = 'validate'. self::toCamelName($ruleName);
                if (method_exists(ValidatesAttributes::class, $hyperfMethod)) {
                    array_push($arr2, $detailRule);
                }elseif (!in_array($detailRule, $arr1)) { //非hyperf规则,且非本组件规则
                    $msg = $this->translator->trans('apihelper.rule_not_defined', ['rule'=> $detailRule]);
                    throw new ValidationException($msg);
                }
            }

            $customRules[$fieldName] = implode('|', $arr1);
            $realRules[$fieldName] = implode('|', $arr2);
        }

        //先执行hyperf的验证
        $validator = $this->validator->make($finalData, $realRules);
        $finalData = $validator->validate();

        //再执行自定义验证
        foreach ($customRules as $field=>$customRule) {
            if(empty($customRule)) continue;

            $fieldValue = $finalData[$field] ?? null;
            $detailRules = explode('|', $customRule);
            foreach ($detailRules as $detailRule) {
                $ruleName = self::parseRuleName($detailRule);
                $optionStr = explode(':', $detailRule)[1] ?? '';
                $optionArr= explode(',', $optionStr);

                $convMethod = 'conver_'. $ruleName;
                if (method_exists($this, $convMethod)) {
                    $fieldValue = call_user_func_array([
                        $this,
                        $convMethod,
                    ], [$fieldValue]);

                }

                $ruleMethod = 'rule_'. $ruleName;
                if (method_exists($this, $ruleMethod)) {
                    $check = call_user_func_array([
                        $this,
                        $ruleMethod,
                    ], [$fieldValue, $field, $optionArr]);
                    if(!$check) break;
                }

                // cb_xxx,调用控制器的方法xxx
                // xxx方法,接受3个参数:$fieldValue, $field, $optionArr;返回结果:若检查通过为true,否则为失败信息
                $controllerMethod = str_replace('cb_', '', $ruleName);
                if (strpos($ruleName, 'cb_') !== false && method_exists($obj, $controllerMethod)) {
                    $chkRes = call_user_func_array([
                        $obj,
                        $controllerMethod,
                    ], [$fieldValue, $field, $optionArr]);
                    if($chkRes!==true) {
                        $this->errors[] = strval($chkRes);
                    }
                }
            }
            $finalData[$field] = $fieldValue;
        }

        $this->errors = array_merge($this->errors, $validator->errors()->getMessages());
        if ($this->errors) {
            return false;
        }

        return $finalData;
    }


    /**
     * 解析规则名
     * @param string $str
     * @return string
     */
    public static function parseRuleName(string $str):string {
        //过滤如gt[0] 或 enum[0,1]
        $res = preg_replace('/\[.*\]/', '', $str);

        if (strpos($res, ':')) { //形如 max:value
            $arr = explode(':', $res);
            $res = $arr[0];
        }

        return trim($res);
    }


    /**
     * 转为驼峰命名
     * @param string $str
     * @return string
     */
    public static function toCamelName(string $str):string {
        $str = str_replace('_', ' ', $str);
        $str = ucwords($str);
        $str = str_replace(' ', '', $str);
        return $str;
    }


    /**
     * 获取错误信息
     * @return array
     */
    public function getError() {
        return $this->errors;
    }


    /**
     * 转换器-整型
     * @param $val
     * @return int
     */
    public function conver_int($val):int {
        return intval($val);
    }


    /**
     * 转换器-整型
     * @param $val
     * @return int
     */
    public function conver_integer($val):int {
        return intval($val);
    }


    /**
     * 转换器-浮点
     * @param $val
     * @return float
     */
    public function conver_float($val):float {
        return floatval($val);
    }


    /**
     * 转换器-布尔值
     * @param $val
     * @return bool
     */
    public function conver_bool($val):bool {
        if (empty($val)
            || in_array(strtolower($val), [
                'false',
                'null',
                'nil',
                'none',
            ])) {

            return false;
        }

        return boolval($val);
    }


    /**
     * 转换器-去掉空格
     * @param $val
     * @return string
     */
    public function conver_trim($val):string {
        return trim(strval($val));
    }


    /**
     * 验证-安全密码
     * @param $val
     * @param string $field
     * @param array $options
     * @return bool
     */
    public function rule_safe_password($val, string $field, array $options=[]):bool {
        if ($val === '') {
            return true;
        }
        if (strlen($val) < 8) {
            $this->errors[] = $this->translator->trans('apihelper.rule_safe_password_len', ['field'=> $field]);
            return false;
        }
        $level = 0;
        if (preg_match('@\d@', $val)) {
            $level++;
        }
        if (preg_match('@[a-z]@', $val)) {
            $level++;
        }
        if (preg_match('@[A-Z]@', $val)) {
            $level++;
        }
        if (preg_match('@[^0-9a-zA-Z]@', $val)) {
            $level++;
        }
        if ($level < 3) {
            $this->errors[] = $this->translator->trans('apihelper.rule_safe_password_simple', ['field'=> $field]);
            return false;
        }

        return true;
    }


    /**
     * 验证-自然数
     * @param $val
     * @param string $field
     * @param array $options
     * @return bool
     */
    public function rule_natural($val, string $field, array $options=[]):bool {
        if ($val === '') {
            return true;
        }
        if (!preg_match('/^[0-9]+$/', $val)) {
            $this->errors[] = $this->translator->trans('apihelper.rule_natural', ['field'=> $field]);
            return false;
        }

        return true;
    }


    /**
     * 验证-中国手机号
     * @param $val
     * @param string $field
     * @param array $options
     * @return bool
     */
    public function rule_cnmobile($val, string $field, array $options=[]):bool {
        $chk = ValidateHelper::isMobile($val);
        if(!$chk) {
            $this->errors[] = $this->translator->trans('apihelper.rule_cnmobile', ['field'=> $field]);
        }

        return boolval($chk);
    }


    /**
     * 验证-枚举
     * @param $val
     * @param string $field
     * @param array $options
     * @return bool
     */
    public function rule_enum($val, string $field, array $options=[]):bool {
        if(empty($options)) {
            return true;
        }elseif ($val==='') {
            return false;
        }

        if (!in_array($val, $options)) {
            $this->errors[] = $this->translator->trans('apihelper.rule_enum', ['field'=> $field, 'values'=>implode(',', $options)]);
            return false;
        }

        return true;
    }



}