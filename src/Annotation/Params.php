<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 15:44
 * Desc: 参数抽象类
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

use Hyperf\Apihelper\ApiAnnotation;
use Hyperf\Di\Annotation\AbstractAnnotation;
use Lkk\Helpers\ArrayHelper;

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
     * @var array 多个规则json的串,主要针对body数据,自动解释为数组
     */
    public $rules;


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



    public function __construct($value = null) {
        parent::__construct($value);
        $this->setName()->setDescription()->setDetailRules()->setRquire()->setType()->setDefault()->setEnum();
    }


    /**
     * 设置字段名
     * @param string $name
     * @return $this
     */
    public function setName(string $name='') {
        if(!empty($name)) {
            $this->name = $name;
        }elseif (!empty($this->key)) {
            $this->name = ApiAnnotation::getFieldByKey($this->key);
        }

        return $this;
    }


    /**
     * 设置字段描述
     * @param string $desc
     * @return $this
     */
    public function setDescription(string $desc='') {
        if(!empty($desc)) {
            $this->description = $desc;
        }else {
            $this->description = $this->description ?: explode('|', strval($this->key))[1] ?? $this->name;
        }

        return $this;
    }


    /**
     * 设置详细规则数组(将规则串拆分为数组)
     * @return $this
     */
    public function setDetailRules() {
        if(!empty($this->rule)) {
            $arr = explode('|', $this->rule);
            array_walk($arr, function(&$item) {
                $item = trim($item);
                return $item;
            });

            $this->_detailRules = array_unique(array_filter($arr));
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
            if($ruleName=='required') {
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
        $type = 'string';

        if(ArrayHelper::dstrpos('int', $this->_detailRules) || ArrayHelper::dstrpos('integer', $this->_detailRules)) {
            $type = 'integer';
        }elseif (ArrayHelper::dstrpos('float', $this->_detailRules)) {
            $type = 'float';
        }

        $this->type = $type;

        return $this;
    }


    /**
     * 设置字段默认值
     * @return $this
     */
    public function setDefault() {
        if(empty($this->_detailRules)) {
            $this->setDetailRules();
        }
        foreach ($this->_detailRules as $detailRule) {
            if(stripos($detailRule, 'default')!==false) {
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
        if(empty($this->_detailRules)) {
            $this->setDetailRules();
        }
        foreach ($this->_detailRules as $detailRule) {
            if(stripos($detailRule, 'enum')!==false) {
                $optionStr = explode(':', $detailRule)[1] ?? '';
                $optionArr = explode(',', $optionStr);

                $this->enum = $optionArr;
                break;
            }
        }

        return $this;
    }



}