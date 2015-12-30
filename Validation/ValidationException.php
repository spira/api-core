<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Validation;

use Illuminate\Contracts\Support\MessageBag;
use Spira\Core\Responder\Contract\TransformableInterface;
use Spira\Core\Responder\Contract\TransformerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ValidationException extends HttpException implements  TransformableInterface
{
    /**
     * The validation error messages.
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Create a new validation exception instance.
     * @param MessageBag $errors
     */
    public function __construct(MessageBag $errors)
    {
        $this->errors = $errors->toArray();
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
        return [
            'message' => 'There was an issue with the validation of provided entity',
            'invalid' => $this->errors,
        ];
    }

    /**
     * @param TransformerInterface $transformer
     * @return mixed
     */
    public function transform(TransformerInterface $transformer)
    {
        return $transformer->transformItem($this->getResponse());
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
