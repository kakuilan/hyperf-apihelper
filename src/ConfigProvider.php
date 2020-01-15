<?php

declare(strict_types=1);
namespace Hyperf\Apihelper;

class ConfigProvider {
    public function __invoke(): array {
        return [
            'dependencies' => [
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
            ],
        ];
    }
}
