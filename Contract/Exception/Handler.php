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
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Spira\Core\Responder\Contract\TransformableInterface;
use Spira\Core\Responder\Transformers\EloquentModelTransformer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        'Symfony\Component\HttpKernel\Exception\HttpException',
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

        $response = [
            'message' => $message,
        ];

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR; //default

        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();

            if (method_exists($e, 'getResponse')) {
                if ($e instanceof TransformableInterface) {
                    $response = $e->transform(\App::make(EloquentModelTransformer::class));
                } else {
                    $response = $e->getResponse();
                }
            }
        }

        if ($debug) {
            $response['debug'] = $debugData;
        }

        return response()->json($response, $statusCode, [], JSON_PRETTY_PRINT);
    }
}