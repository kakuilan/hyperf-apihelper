<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 15:35
 * Desc: 响应注解
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class ApiResponse extends AbstractAnnotation {


    /**
     * @var string httpCode
     */
    public $code;


    /**
     * @var string 响应描述
     */
    public $description;


    /**
     * @var array 响应json结构,将自动转数组
     */
    public $schema;


    /**
     * @var array 接口响应的基本json结构
     */
    public static $baseSchema = [
        'status' => true,
        'msg' => 'success',
        'code' => 200,
        'data' => [],
    ];


    public function __construct($value = null) {
        parent::__construct($value);
        if (is_array($this->description)) {
            $this->description = json_encode($this->description, JSON_UNESCAPED_UNICODE);
        }

    }


    /**
     * 处理接口成功数据
     * @param array $data
     * @param string $msg
     * @param array|null $result
     * @return array
     */
    final public static function doSuccess(array $data=[], string $msg='success', array $result=null):array {
        if(is_null($result) || empty($result)) $result = self::$baseSchema;

        if($data === false || $data === true) {
            $result['data'] = [];
        }elseif (is_array($data) || is_object($data)) {
            $result['data'] = array_merge((array)$result['data'], (array)$data);
        }else{
            $result['data'] = strval($data);
        }

        $result = [
            'status' => true,
            'msg' => $msg,
            'code' => 200,
            'data' => $result['data']
        ];

        return $result;
    }


    /**
     * 处理接口失败数据
     * @param string|array $code 错误码(如400);或错误信息数组[错误码,提示信息],如 [400, '操作失败']
     * @param array $trans 翻译数组
     * @return array
     */
    final public static function doFail($code='400', array $trans=[]):array {
        if(is_array($code)) {
            $msg = end($code);
            $code = reset($code);
        }else{
            $msg = 'fail';
        }

        $codeNo = ($code!=$msg && is_numeric($code)) ? intval($code) : 400;

        $result = [
            'status' => false,
            'msg' => $msg,
            'code' => $codeNo,
            'data' => []
        ];

        return $result;
    }

}