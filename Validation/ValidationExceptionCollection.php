<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Validation;

use Spira\Core\Responder\Contract\TransformableInterface;
use Spira\Core\Responder\Contract\TransformerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ValidationExceptionCollection extends HttpException implements TransformableInterface
{
    /**
     * @var ValidationException[]
     */
    private $exceptions;

    public function __construct(array $exceptions)
    {
        $this->exceptions = $exceptions;
    }

    /**
     * Returns the status code.
     *
     * @return int An HTTP response status code
     */
    public function getStatusCode()
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    /**
     * Return the response instance.
     *
     * @return array
     */
    public function getResponse()
    {
        $responses = [];
        foreach ($this->exceptions as $exception) {
            if (! is_null($exception)) {
                $responses[] = $exception->getErrors();
            } else {
                $responses[] = null;
            }
        }

        return $responses;
    }

    /**
     * @param TransformerInterface $transformer
     * @return mixed
     */
    public function transform(TransformerInterface $transformer)
    {
        return [
            'message' => 'There was an issue with the validation of provided entity',
            'invalid' => $transformer->transformCollection($this->getResponse()),
        ];
    }
}