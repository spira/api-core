<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Contract\Exception;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spira\Core\Responder\Transformers\ValidationExceptionTransformer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Spira\Core\Responder\Contract\TransformableInterface;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Exception               $e
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        $debug = env('APP_DEBUG', false);

        $message = $e->getMessage();
        if (! $message) {
            $message = 'An error occurred';
        }

        $debugData = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ];

        $responseData = [
            'message' => $message,
        ];

        if ($e instanceof HttpExceptionInterface) {
            if (method_exists($e, 'getResponse')) {
                if ($e instanceof TransformableInterface) {
                    $responseData = $e->transform(app(ValidationExceptionTransformer::class));
                } else {
                    $responseData = $e->getResponse();
                }
            }
        }

        if ($debug) {
            $responseData['debug'] = $debugData;
        }

        $response = parent::render($request, $e);

        $response = new JsonResponse($responseData, $response->getStatusCode(), $response->headers->all(), JSON_PRETTY_PRINT);

        $response->exception = $e;

        app('Asm89\Stack\CorsService')->addActualRequestHeaders($response, $request);

        return $response;
    }
}
