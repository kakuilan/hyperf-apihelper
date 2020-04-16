<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/16
 * Time: 13:34
 * Desc: apihelper英文
 */


return [
    //验证规则
    'rule_not_defined'           => 'The :rule validation rule not defined.',
    'rule_callback_error_result' => 'The :rule controller method return exception.',
    'rule_safe_password_simple'  => 'The :field passwords are too simple, must contain numbers, letters, and other symbols.',
    'rule_natural'               => 'The :field not a natural number.',
    'rule_cnmobile'              => 'The :field not a chinese phone number.',
    'rule_cncreditno'            => 'The :field not a chinese ID card number.',
    'rule_enum'                  => 'The :field must be on of :values',
    'rule_object'                => 'The :field must be object(array of key-value pairs)',

    //响应体错误码
    '200'                        => 'Success',
    '400'                        => 'Fail',
    '401'                        => 'No access rights',
    '402'                        => 'Logged in on other devices',
    '403'                        => 'Not logged in or logged out',
    '404'                        => 'The requested content does not exist',
    '412'                        => 'Missing necessary parameters, or parameter type error',
    '500'                        => 'Server error',
];