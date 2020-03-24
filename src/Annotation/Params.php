<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/6
 * Time: 16:32
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Annotation;

use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Di\Annotation\AbstractAnnotation;


/**
 * Class Params
 * @package Hyperf\Apihelper\Annotation
 */
class Params extends AbstractAnnotation {


    /**
     * @var string 在哪个结构
     */
    public $in;


    /**
     * @var string 字段key,相当于"name[|description]"
     */
    public $key;


    /**
     * @var string 单个规则字符串
     */
    public $rule;


    /**
     * @var string 默认值
     */
    public $default;


    /**
     * @var string 字段名
     */
    public $name;


    /**
     * @var string 字段描述
     */
    public $description;


    /**
     * @var array 详细规则数组
     */
    public $_detailRules = [];


    /**
     * @var bool 是否必须
     */
    public $required = false;


    /**
     * @var string 字段类型
     */
    public $type;


    /**
     * @var array 字段枚举值
     */
    public $enum;


    /**
     * Params constructor.
     * @param mixed $value
     */
    public function __construct($value = null) {
        parent::__construct($value);
        $this->setName()->setDescription()->setDetailRules()->setRquire()->setType()->setDefault()->setEnum();
    }


    /**
     * 设置key
     * @param string $key
     * @return $this
     */
    public function setKey(string $key = '') {
        if (!empty($key)) {
            $this->key = $key;
        }

        return $this;
    }


    /**
     * 设置字段名
     * @param string $name
     * @return $this
     */
    public function setName(string $name = '') {
        if (!empty($name)) {
            $this->name = $name;
        } elseif (!empty($this->key)) {
            $this->name = ApiAnnotation::getFieldByKey($this->key);
        }

        return $this;
    }


    /**
     * 设置字段描述
     * @param string $desc
     * @return $this
     */
    public function setDescription(string $desc = '') {
        if (!empty($desc)) {
            $this->description = $desc;
        } else {
            $this->description = $this->description ?: explode('|', strval($this->key))[1] ?? $this->name;
        }

        return $this;
    }


    /**
     * 设置详细规则数组(将规则串拆分为数组)
     * @return $this
     */
    public function setDetailRules() {
        if (!empty($this->rule)) {
            $this->_detailRules = ApiAnnotation::parseDetailsByRule($this->rule);
        }

        return $this;
    }


    /**
     * 设置字段是否必填
     * @return $this
     */
    public function setRquire() {
        foreach ($this->_detailRules as $detailRule) {
            $ruleName = ApiAnnotation::parseRuleName($detailRule);
            //一定要等于"required",因为还有其他规则名如required_without_all等
            if ($ruleName == 'required') {
                $this->required = true;
                break;
            }
        }

        return $this;
    }


    /**
     * 设置字段类型
     * @return $this
     */
    public function setType() {
        $type = '';
        if (in_array('int', $this->_detailRules) || in_array('integer', $this->_detailRules)) {
            $type = 'integer';
        } elseif (in_array('float', $this->_detailRules)) {
            $type = 'float';
        } elseif (in_array('number', $this->_detailRules) || in_array('numeric', $this->_detailRules)) { // numeric 是hyperf官方验证规则
            $type = 'number';
        } elseif (in_array('bool', $this->_detailRules) || in_array('boolean', $this->_detailRules)) { // boolean 是hyperf官方验证规则
            $type = 'boolean';
        } elseif (in_array('array', $this->_detailRules)) { // array 是hyperf官方验证规则
            $type = 'array';
        } elseif (in_array('object', $this->_detailRules)) { // object 是swagger的数据类型
            $type = 'object';
        }

        if(empty($type)) {
            $type = ApiAnnotation::getTypeByRule($this->rule);
        }

        $this->type = $type;

        return $this;
    }


    /**
     * 设置字段默认值
     * @return $this
     */
    public function setDefault() {
        if (empty($this->_detailRules)) {
            $this->setDetailRules();
        }
        foreach ($this->_detailRules as $detailRule) {
            if (stripos($detailRule, 'default') !== false) {
                $optionStr = explode(':', $detailRule)[1] ?? '';
                $optionArr = explode(',', $optionStr);
                if ($optionStr == '' && empty($optionArr)) {
                    array_push($optionArr, '');
                }
                $len = count($optionArr);

                $this->default = $len == 1 ? current($optionArr) : $optionArr;
                break;
            }
        }

        return $this;
    }


    /**
     * 设置字段枚举值
     * @return $this
     */
    public function setEnum() {
        if (empty($this->_detailRules)) {
            $this->setDetailRules();
        }
        foreach ($this->_detailRules as $detailRule) {
            if (stripos($detailRule, 'enum') !== false) {
                $optionStr = explode(':', $detailRule)[1] ?? '';
                $optionArr = explode(',', $optionStr);

                $this->enum = $optionArr;
                break;
            }
        }

        return $this;
    }


}