<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/15
 * Time: 15:03
 * Desc:
 */

return [
    'output_file' => BASE_PATH . '/public/swagger.json',
    'swagger' => '2.0', //OpenAPI 规范的版本
    'info' => [
        'description' => 'hyperf swagger api desc', //API 文档描述
        'version' => '1.0.0', //API 文档版本
        'title' => 'HYPERF API DOC', //API 文档标题
    ],
    'host' => 'hyperf.io', //主机名
    'schemes' => ['http'], //协议
    'basePath' => '', //基础路径,如/v1
];