<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/3/10
 * Time: 10:41
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper;

class ConfigProvider {

    public function __invoke(): array {
        return [
            'dependencies' => [
                \Hyperf\Apihelper\Validation\ValidationInterface::class => \Hyperf\Apihelper\Validation\Validator::class,
                \Hyperf\HttpServer\Router\DispatcherFactory::class      => \Hyperf\Apihelper\DispatcherFactory::class,
            ],
            'commands'     => [
            ],
            'annotations'  => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish'      => [
                [
                    'id'          => 'apihelper config',
                    'description' => 'The config for apihelper.',
                    'source'      => __DIR__ . '/../publish/apihelper.php', //源文件
                    'destination' => BASE_PATH . '/config/autoload/apihelper.php', //目标路径
                ],
                [
                    'id'          => 'validation en trans',
                    'description' => 'The translate for validation.',
                    'source'      => __DIR__ . '/../publish/languages/en/apihelper.php', //源文件
                    'destination' => BASE_PATH . '/storage/languages/en/apihelper.php', //目标路径
                ],
                [
                    'id'          => 'validation cn trans',
                    'description' => 'The translate for validation.',
                    'source'      => __DIR__ . '/../publish/languages/zh_CN/apihelper.php', //源文件
                    'destination' => BASE_PATH . '/storage/languages/zh_CN/apihelper.php', //目标路径
                ],

                //swagger-ui
                [
                    'id'          => 'icon-16',
                    'description' => '',
                    'source'      => __DIR__ . '/../publish/swagger/favicon-16x16.png', //源文件
                    'destination' => BASE_PATH . '/public/swagger/favicon-16x16.png', //目标路径
                ],
                [
                    'id'          => 'icon-32',
                    'description' => '',
                    'source'      => __DIR__ . '/../publish/swagger/favicon-32x32.png', //源文件
                    'destination' => BASE_PATH . '/public/swagger/favicon-32x32.png', //目标路径
                ],
                [
                    'id'          => 'fonts css',
                    'description' => '',
                    'source'      => __DIR__ . '/../publish/swagger/fonts.css', //源文件
                    'destination' => BASE_PATH . '/public/swagger/fonts.css', //目标路径
                ],
                [
                    'id'          => 'html',
                    'description' => '',
                    'source'      => __DIR__ . '/../publish/swagger/index.html', //源文件
                    'destination' => BASE_PATH . '/public/swagger/index.html', //目标路径
                ],
                [
                    'id'          => 'ui css',
                    'description' => '',
                    'source'      => __DIR__ . '/../publish/swagger/swagger-ui.css', //源文件
                    'destination' => BASE_PATH . '/public/swagger/swagger-ui.css', //目标路径
                ],
                [
                    'id'          => 'bundle js',
                    'description' => '',
                    'source'      => __DIR__ . '/../publish/swagger/swagger-ui-bundle.js', //源文件
                    'destination' => BASE_PATH . '/public/swagger/swagger-ui-bundle.js', //目标路径
                ],
                [
                    'id'          => 'preset js',
                    'description' => '',
                    'source'      => __DIR__ . '/../publish/swagger/swagger-ui-standalone-preset.js', //源文件
                    'destination' => BASE_PATH . '/public/swagger/swagger-ui-standalone-preset.js', //目标路径
                ],
            ],
        ];
    }

}
