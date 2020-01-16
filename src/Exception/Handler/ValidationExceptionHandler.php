<?php
/**
 * Created by PhpStorm.
 * User: kakuilan
 * Date: 2020/1/16
 * Time: 16:25
 * Desc:
 */

declare(strict_types=1);
namespace Hyperf\Apihelper\Exception\Handler;

use Hyperf\Apihelper\Annotation\ApiResponse;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ValidationExceptionHandler extends ExceptionHandler {

    public function handle(Throwable $throwable, ResponseInterface $response) {
        $this->stopPropagation();

        /** @var \Hyperf\Validation\ValidationException $throwable */
        $msg = $throwable->validator->errors()->first();
        $res = ApiResponse::doFail([400, $msg]);

        return $response->withBody(new SwooleStream(json_encode($res)));
    }

    public function isValid(Throwable $throwable): bool {
        return $throwable instanceof ValidationException;
    }
}