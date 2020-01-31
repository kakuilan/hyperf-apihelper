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
use Hyperf\Apihelper\Exception\ValidationException as MyValidationException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException as HyperfValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ValidationExceptionHandler extends ExceptionHandler {

    public function handle(Throwable $throwable, ResponseInterface $response) {
        $this->stopPropagation();

        if ($throwable instanceof HyperfValidationException) {
            $msg = $throwable->validator->errors()->first();
            $res = ApiResponse::doFail([400, $msg]);
        } elseif ($throwable instanceof MyValidationException) {
            $res = ApiResponse::doFail([400, $throwable->getMessage()]);
        } else {
            $res = ApiResponse::doFail([400, 'unknow error']);
        }

        return $response->withBody(new SwooleStream(json_encode($res)));
    }

    public function isValid(Throwable $throwable): bool {
        return $throwable instanceof HyperfValidationException || $throwable instanceof MyValidationException;
    }

}