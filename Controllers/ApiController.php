<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller;
use Spira\Core\Contract\Exception\BadRequestException;
use Spira\Core\Model\Collection\Collection;
use Spira\Core\Model\Model\BaseModel;
use Spira\Core\Responder\Contract\TransformerInterface;
use Spira\Core\Responder\Response\ApiResponse;

abstract class ApiController extends Controller
{
    use AuthorizesRequestsTrait;

    protected $paginatorDefaultLimit = 10;
    protected $paginatorMaxLimit = 50;

    /**
     * @var TransformerInterface
     */
    protected $transformer;

    /**
     * Enable permissions checks.
     */
    protected $permissionsEnabled = false;

    /**
     * Name of the default role to check against
     * Designed for default rules
     * Should be set to false to enable route based permissions.
     */
    protected $defaultRole = 'admin';

    public function __construct(TransformerInterface $transformer)
    {
        $this->transformer = $transformer;
        $this->middleware('transaction');
    }

    /**
     * @return ApiResponse
     */
    public function getResponse()
    {
        return new ApiResponse();
    }

    /**
     * @return TransformerInterface
     */
    public function getTransformer()
    {
        return $this->transformer;
    }

    /**
     * @param Collection|BaseModel $modelOrCollection
     * @param Request $request
     * @return mixed
     */
    protected function getWithNested($modelOrCollection, Request $request)
    {
        $nested = $request->headers->get('With-Nested');
        if (! $nested) {
            return $modelOrCollection;
        }

        $requestedRelations = explode(', ', $nested);

        try {
            $modelOrCollection->load($requestedRelations);
        } catch (\BadMethodCallException $e) {
            throw new BadRequestException(sprintf('Invalid `With-Nested` request - one or more of the following relationships do not exist for %s:[%s]', get_class($modelOrCollection), $nested), null, $e);
        }

        return $modelOrCollection;
    }

    /**
     * Authorize a given action against a set of arguments.
     *
     * @param  mixed  $permission
     * @param  mixed|array  $arguments
     * @return void
     */
    public function checkPermission($permission, $arguments = [])
    {
        if (! $this->permissionsEnabled) {
            return;
        }

        if ($this->defaultRole) {
            $permission = $this->defaultRole;
        }

        $this->authorize($permission, $arguments);
    }

    /**
     * Get the value of the primary key in a request.
     * @param BaseModel $entityModel
     * @param array $requestEntity
     * @return null
     */
    public function getKeyFromRequestEntity(BaseModel $entityModel, array $requestEntity)
    {
        if (! isset($requestEntity[$entityModel->getPrimaryKey()])) {
            return;
        }

        return $requestEntity[$entityModel->getPrimaryKey()];
    }

    /**
     * Override for custom functionality.
     *
     * @param BaseModel $model
     * @param $requestEntity
     * @return BaseModel
     */
    protected function fillModel(BaseModel $model, $requestEntity)
    {
        return $model->fill($requestEntity);
    }

    /**
     *  Override for custom functionality.
     *
     * @param $baseModel $model
     * @param Collection|BaseModel[] $existingModels
     * @param Collection|array $requestCollection
     * @return Collection|array
     */
    protected function fillModels(BaseModel $baseModel, $existingModels, $requestCollection)
    {
        return $baseModel->hydrateRequestCollection($requestCollection, $existingModels);
    }
}
