<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/31
 * Time: 14:01
 * Desc:
 */

use Psr\Http\Message\ServerRequestInterface;

return [
    //api配置
    'api' => [
        //控制器前置方法,每次先于具体动作之前执行,该方法必须仅接受一个ServerRequestInterface类型的参数,并返回
        //形如, fn(ServerRequestInterface $request):ServerRequestInterface
        'controller_antecedent' => 'initialization',
    ],

    //swagger文档配置
    'swagger' => [
        'output_json' => true, // 是否生成json文件,以供swagger-ui使用;开发环境打开,为true;生产环境关闭,为false.
        'output_dir' => BASE_PATH . '/public/swagger', //swagger目录,必须在public下,可不改
        'output_basename' => 'swagger', //基本名
        'swagger' => '2.0', //OpenAPI 规范的版本
        'info' => [
            'description' => 'hyperf swagger api desc', //API 文档描述
            'version' => '1.0.0', //API 文档版本
            'title' => 'HYPERF API DOC', //API 文档标题
        ],
        'host' => 'hyperf.io', //站点域名
        'basePath' => '', //基础路径,可不改
        'schemes' => ['http'], //协议
        'securityDefinitions' => [
            'jwt' => [
                'type' => 'apiKey',
                'name' => 'Authorization',
                'in' => 'header',
            ],
        ],
        'security' => [
            ['jwt' => [],],
        ],
    ],
];