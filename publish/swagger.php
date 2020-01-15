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
    'swagger' => '3.24.3',
    'info' => [
        'description' => 'hyperf swagger api desc',
        'version' => '1.0.0',
        'title' => 'HYPERF API DOC',
    ],
    'host' => 'hyperf.io',
    'schemes' => ['http']
];