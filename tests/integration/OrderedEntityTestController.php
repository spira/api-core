<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests\integration;

use Illuminate\Http\Request;
use Spira\Core\Controllers\EntityController;
use Spira\Core\Controllers\LocalizableTrait;
use Spira\Core\Model\Test\OrderedTestEntity;
use Spira\Core\Responder\Transformers\EloquentModelTransformer;

class OrderedEntityTestController extends EntityController
{
    use LocalizableTrait;

    protected $permissionsEnabled = false;

    protected $defaultRole = 'user';

    public function __construct(OrderedTestEntity $model, EloquentModelTransformer $transformer)
    {
        parent::__construct($model, $transformer);
    }

    /**
     * Get ordered entities.
     *
     * @param Request $request
     * @return ApiResponse
     */
    public function getOrdered()
    {
        $collection = $this->getAllEntities();

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->collection($collection);
    }
}
