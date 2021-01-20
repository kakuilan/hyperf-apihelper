<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/3/9
 * Time: 15:52
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Exception\Handler;

use Hyperf\Apihelper\Exception\ValidationException as MyValidationException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException as HyperfValidationException;
use Kph\Consts;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;


/**
 * Class ValidationExceptionHandler
 * @package Hyperf\Apihelper\Exception\Handler
 */
class ValidationExceptionHandler extends ExceptionHandler {

    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;


    /**
     * 异常处理
     * @param Throwable $throwable
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function handle(Throwable $throwable, ResponseInterface $response) {
        $conf            = $this->container->get(ConfigInterface::class);
        $baseCtrlClass   = $conf->get('apihelper.api.base_controller');
        $showDetailError = $conf->get('apihelper.api.show_params_detail_error');

        $this->stopPropagation();

        if ($throwable instanceof HyperfValidationException) {
            $res = $showDetailError ? $baseCtrlClass::doValidationFail($throwable->validator->errors()->first()) : $baseCtrlClass::doValidationFail();
        } elseif ($throwable instanceof MyValidationException) {
            $res = $showDetailError ? $baseCtrlClass::doValidationFail($throwable->getMessage()) : $baseCtrlClass::doValidationFail();
        } else {
            $res = $showDetailError ? $baseCtrlClass::doValidationFail(Consts::UNKNOWN) : $baseCtrlClass::doValidationFail();
        }

        return $response->withBody(new SwooleStream(json_encode($res)));
    }


    /**
     * 是否有效的异常
     * @param Throwable $throwable
     * @return bool
     */
    public function isValid(Throwable $throwable): bool {
        return $throwable instanceof HyperfValidationException || $throwable instanceof MyValidationException;
    }

}