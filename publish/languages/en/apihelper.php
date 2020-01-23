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
    'rule_not_defined' => 'The :rule validation rule not defined.',
    'rule_callback_error_result' => 'The :rule controller method return exception.',
    'rule_safe_password_len' => 'The :field safety password length is at least 8.',
    'rule_safe_password_simple' => 'The :field passwords are too simple, must contain numbers, letters, and other symbols.',
    'rule_natural' => 'The :field not a natural number.',
    'rule_cnmobile' => 'The :field not a phone number.',
    'rule_enum' => 'The :field must be on of :values',

    //响应体错误码
    '200' => 'Success',
    '400' => 'Fail',
    '401' => 'No access rights',
    '402' => 'Logged in on other devices',
    '403' => 'Not logged in or logged out',
    '404' => 'The requested content does not exist',
    '500' => 'Server error',
];