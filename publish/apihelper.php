<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/31
 * Time: 14:01
 * Desc:
 */

use Psr\Http\Message\ServerRequestInterface;
use Hyperf\Apihelper\Controller\BaseController;

return [
    //api配置
    'api'     => [
        //是否显示具体的参数错误,生产环境可关闭
        'show_params_detail_error' => env('APP_DEBUG', false),
        //基本控制器,app中的控制器必须都继承自该类
        'base_controller'          => BaseController::class,
        //控制器前置方法,每次先于具体动作之前执行,该方法必须仅接受一个ServerRequestInterface类型的参数,并返回.
        //形如, fn(ServerRequestInterface $request):ServerRequestInterface
        'controller_antecedent'    => 'initialization',
        //控制器拦截方法,每次先于具体动作之前执行;若该方法返回非空的数组或字符串,则停止执行后续的具体动作.
        //形如, fn(string $controller, string $action, string $route):mixed
        'controller_intercept'     => 'interceptor',
        //控制器后置方法,每次在具体动作之后执行;该方法必须接受一个ServerRequestInterface类型、一个ResponseInterface类型的两个参数,无返回值.
        //形如, fn(ServerRequestInterface $request, ResponseInterface $response):void
        'controller_subsequent'    => 'after',
        //是否使用版本号路径前缀
        'use_version_path'         => true,
    ],

    //swagger文档配置
    'swagger' => [
        'output_json'         => env('API_DOC', false), // 是否生成json文件,以供swagger-ui使用;开发环境打开,为true;生产环境关闭,为false.
        'output_dir'          => BASE_PATH . '/public/swagger', //swagger目录,必须在public下,可不改
        'output_basename'     => 'swagger', //基本名
        'swagger'             => '2.0', //OpenAPI 规范的版本
        'info'                => [
            'title'       => 'HYPERF API DOC', //API文档标题
            'version'     => '1.0.0', //API版本
            //API文档描述,支持markdown语法
            'description' => <<<EOF
#####  hyperf swagger api desc
EOF,
        ],
        'host'                => env('APP_URL', 'localhost'), //站点域名或URL
        'basePath'            => '', //基础路径,可不改
        'schemes'             => ['http'], //协议
        'securityDefinitions' => [
            'jwt' => [
                'type' => 'apiKey',
                'name' => 'Authorization',
                'in'   => 'header',
            ],
        ],
        'security'            => [
            ['jwt' => [],],
        ],
    ],
];