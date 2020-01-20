<?php

declare(strict_types=1);
namespace Hyperf\Apihelper;

class ConfigProvider {
    public function __invoke(): array {
        return [
            'dependencies' => [
                \Hyperf\Apihelper\Validation\ValidationInterface::class => \Hyperf\Apihelper\Validation\Validation::class,
                \Hyperf\HttpServer\Router\DispatcherFactory::class => \Hyperf\Apihelper\DispathcerFactory::class,
            ],
            'commands' => [
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for swagger.',
                    'source' => __DIR__ . '/../publish/swagger.php', //源文件
                    'destination' => BASE_PATH . '/config/autoload/swagger.php', //目标路径
                ],
                [
                    'id' => 'config',
                    'description' => 'The translate for validation.',
                    'source' => __DIR__ . '/../publish/en/apihelper.php', //源文件
                    'destination' => BASE_PATH . '/storage/languages/en/apihelper.php', //目标路径
                ],
                [
                    'id' => 'config',
                    'description' => 'The translate for validation.',
                    'source' => __DIR__ . '/../publish/zh_CN/apihelper.php', //源文件
                    'destination' => BASE_PATH . '/storage/languages/zh_CN/apihelper.php', //目标路径
                ],
            ],
        ];
    }
}
