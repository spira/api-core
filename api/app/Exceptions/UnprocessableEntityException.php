<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UnprocessableEntityException extends HttpException
{
    /**
     * Create a new Unprocessable Entity exception instance.
     *
     * @param string     $message
     * @param int        $code
     * @param \Exception $previous
     *
     * @return void
     */
    public function __construct($message = 'Unprocessable Entity.', $code = 0, Exception $previous = null)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, $message, $previous);
    }
}
