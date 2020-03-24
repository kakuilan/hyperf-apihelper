<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/3/9
 * Time: 15:52
 * Desc:
 */

declare(strict_types=1);

namespace Hyperf\Apihelper\Exception\Handler;

use Hyperf\Apihelper\Annotation\ApiResponse;
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
        $showDetailError = $conf->get('apihelper.api.show_params_detail_error');

        $this->stopPropagation();

        if ($throwable instanceof HyperfValidationException) {
            $res = $showDetailError ? ApiResponse::doFail([400, $throwable->validator->errors()->first()]) : ApiResponse::doFail(412);
        } elseif ($throwable instanceof MyValidationException) {
            $res = $showDetailError ? ApiResponse::doFail([400, $throwable->getMessage()]) : ApiResponse::doFail(412);
        } else {
            $res = $showDetailError ? ApiResponse::doFail([400, Consts::UNKNOWN]) : ApiResponse::doFail(412);
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