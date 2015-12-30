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

    /**
     * Create a new validation collection exception instance.
     * @param array $exceptions
     * @param string $message
     * @param \Exception $previous
     * @param array $headers
     * @param int $code
     */
    public function __construct(array $exceptions, $message = 'There was an issue with the validation of provided entities', \Exception $previous = null, array $headers = [], $code = 0)
    {
        $this->exceptions = $exceptions;

        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $previous, $headers, $code);
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
            'message' => $this->getMessage(),
            'invalid' => $transformer->transformCollection($this->getResponse()),
        ];
    }
}
