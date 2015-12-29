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
use Spira\Core\Model\Model\BaseModel;
use Spira\Core\Responder\Response\ApiResponse;

abstract class LinkedEntityController extends AbstractRelatedEntityController
{
    public function getAll(Request $request, $id)
    {
        $model = $this->findParentEntity($id);

        $childEntities = $this->findAllChildren($model);
        $childEntities = $this->getWithNested($childEntities, $request);

        $this->checkPermission(static::class.'@getAll', ['model' => $model, 'children' => $childEntities]);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->collection($childEntities);
    }

    public function attachOne(Request $request, $id, $childId)
    {
        $parent = $this->findParentEntity($id);

        $requestEntity = $request->json()->all();
        if (! empty($requestEntity)) {
            $childModel = $this->findOrNewChildEntity($childId, $parent);
            $this->validateRequest($requestEntity, $this->getValidationRules($childId, $requestEntity), $childModel, true);
            $childModel->fill($requestEntity)->save();
        } else {
            $childModel = $this->findOrFailChildEntity($childId, $parent);
        }

        $this->checkPermission(static::class.'@attachOne', ['model' => $parent, 'children' => $childModel]);

        $this->getRelation($parent)->attach($childModel, $this->getPivotValues($childModel));

        return $this->getResponse()->created();
    }

    public function attachMany(Request $request, $id)
    {
        return $this->processMany($request, $id, 'attach');
    }

    public function syncMany(Request $request, $id)
    {
        return $this->processMany($request, $id, 'sync');
    }

    public function detachOne($id, $childId)
    {
        $parent = $this->findParentEntity($id);
        $childModel = $this->findOrFailChildEntity($childId, $parent);

        $this->checkPermission(static::class.'@detachOne', ['model' => $parent, 'children' => $childModel]);
        $this->getRelation($parent)->detach($childModel);

        return $this->getResponse()->noContent();
    }

    public function detachAll($id)
    {
        $parent = $this->findParentEntity($id);

        $this->checkPermission(static::class.'@detachAll', ['model' => $parent]);
        $this->getRelation($parent)->detach();

        return $this->getResponse()->noContent();
    }

    protected function processMany(Request $request, $id, $method)
    {
        $parent = $this->findParentEntity($id);

        $requestCollection = $request->json()->all();
        $this->validateRequestCollection($requestCollection, $this->getChildModel(), true);

        $existingChildren = $this->findChildrenCollection($requestCollection, $parent);
        $childModels = $this->getChildModel()->hydrateRequestCollection($requestCollection, $existingChildren);

        $this->checkPermission(static::class.'@'.$method.'Many', ['model' => $parent, 'children' => $childModels]);

        $this->preSync($parent, $childModels);

        $this->saveNewItemsInCollection($childModels);
        $this->getRelation($parent)->{$method}($this->makeSyncList($childModels, $requestCollection));

        $this->postSync($parent, $childModels);

        $transformed = $this->getTransformer()->transformCollection($this->findAllChildren($parent), ['_self']);

        $responseCollection = collect($transformed)->map(function ($entity) {
            return ['_self' => $entity['_self']];
        })->toArray();

        return $this->getResponse()->collection($responseCollection, ApiResponse::HTTP_CREATED);
    }

    protected function fetchChildFromRelation($id, BaseModel $parent)
    {
        return $this->getRelation($parent)->getModel()->findOrFail($id);
    }
}
