<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/16
 * Time: 13:34
 * Desc: apihelper中文
 */


return [
    //验证规则
    'rule_not_defined' => ':rule 验证规则未定义',
    'rule_callback_error_result' => ':rule 控制器验证方法返回结果异常',
    'rule_safe_password_len' => ':field 安全密码长度最少为8位',
    'rule_safe_password_simple' => ':field 密码太简单，必须包含数字、大小写字母、其它符号中的三种及以上',
    'rule_natural' => ':field 不是合法的自然数',
    'rule_cnmobile' => ':field 不是合法的手机号',
    'rule_enum' => ':field 必须是 :values 其中之一',

    //响应体错误码
    '200' => 'Success',
    '400' => 'Fail',
    '401' => '无访问权限',
    '402' => '已在其他设备上登录',
    '403' => '未登录或登录失效',
    '404' => '请求内容不存在',
    '412' => '缺少必要的参数,或参数类型错误',
    '500' => '服务器错误',
];