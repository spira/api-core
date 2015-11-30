<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Controllers;

use App\Models\TestEntity;
use Spira\Core\Responder\Transformers\EloquentModelTransformer;

class TestController extends EntityController
{

    protected $permissionsEnabled = true;

    protected $defaultRole = 'user';

    public function __construct(TestEntity $model, EloquentModelTransformer $transformer)
    {
        parent::__construct($model, $transformer);
    }

    public function urlEncode($id)
    {
        return $this->getResponse()->item(['test' => $id]);
    }

    /**
     * Test a standard internal exception.
     */
    public function internalException()
    {
        throw new \RuntimeException('Something went wrong');
    }

    /**
     * Test a fatal exception (has to be tested with guzzle to stop phpunit halting).
     *
     * @codeCoverageIgnore
     */
    public function fatalError()
    {
        call_to_non_existent_function();
    }
}