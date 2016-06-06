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
use Spira\Core\Model\Model\ElasticSearchIndexer;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

abstract class ChildEntityController extends AbstractRelatedEntityController
{
    /**
     * Get all entities.
     *
     * @param Request $request
     * @param string $id
     * @return ApiResponse
     */
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

    /**
     * Get one entity.
     *
     * @param Request $request
     * @param  string $id
     * @param bool|string $childId
     * @return ApiResponse
     */
    public function getOne(Request $request, $id, $childId = false)
    {
        $parent = $this->findParentEntity($id);

        //If the child id is not passed in the url, fall back to the child id being the parent id (for the case where the relationship is HasOne with primary key being foreign parent id)
        if ($this->childIdCanFallbackToParent($childId, $parent)) {
            $childId = $parent->getKey();
        }

        $childModel = $this->findOrFailChildEntity($childId, $parent);
        $childModel = $this->getWithNested($childModel, $request);

        $this->checkPermission(static::class.'@getOne', ['model' => $parent, 'children' => $childModel]);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->item($childModel);
    }

    /**
     * Post a new entity.
     *
     * @param string $id
     * @param  Request $request
     * @return ApiResponse
     * @throws \Exception
     * @throws \Exception|null
     */
    public function postOne(Request $request, ElasticSearchIndexer $searchIndexer, $id)
    {
        $parent = $this->findParentEntity($id);
        $childModel = $this->getChildModel()->newInstance();

        $requestEntity = $request->json()->all();
        $this->validateRequest($requestEntity, $this->getValidationRules($id, $requestEntity));

        $this->fillModel($childModel, $request->json()->all());

        $this->checkPermission(static::class.'@postOne', ['model' => $parent, 'children' => $childModel]);

        $this->getRelation($parent)->save($childModel);

        // Children is auto updated, so need only to update parent
        $searchIndexer->reindexOne($parent, []);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->created()
            ->item($childModel);
    }

    /**
     * Add many entities.
     * Internally make use of Relation::saveMany().
     *
     * @param string $id
     * @param  Request $request
     * @return ApiResponse
     */
    public function postMany(Request $request, ElasticSearchIndexer $searchIndexer, $id)
    {
        $parent = $this->findParentEntity($id);

        $requestCollection = $request->json()->all();
        $this->validateRequestCollection($requestCollection, $this->getChildModel());

        $existingModels = $this->findChildrenCollection($requestCollection, $parent);

        $childModels = $this->fillModels($this->getChildModel(), $existingModels, $requestCollection);

        $this->checkPermission(static::class.'@postMany', ['model' => $parent, 'children' => $childModels]);

        $this->getRelation($parent)->saveMany($childModels);

        // Children is auto updated, so need only to update parent
        $searchIndexer->reindexOne($parent, []);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->created()
            ->collection($childModels);
    }

    /**
     * Put an entity.
     *
     * @param  Request $request
     * @param  string $id
     * @param bool|string $childId
     * @return ApiResponse
     */
    public function putOne(Request $request, ElasticSearchIndexer $searchIndexer, $id, $childId = false)
    {
        $parent = $this->findParentEntity($id);

        //If the child id is not passed in the url, fall back to the child id being the parent id (for the case where the relationship is HasOne with primary key being foreign parent id)
        if ($this->childIdCanFallbackToParent($childId, $parent)) {
            $this->checkEntityIdMatchesRoute($request, $id, $this->getChildModel());
            $childId = $parent->getKey();
        } else {
            $this->checkEntityIdMatchesRoute($request, $childId, $this->getChildModel());
        }

        $childModel = $this->findOrNewChildEntity($childId, $parent);

        $requestEntity = $request->json()->all();
        $this->validateRequest($requestEntity, $this->getValidationRules($childId, $requestEntity));

        $this->fillModel($childModel, $request->json()->all());

        $this->checkPermission(static::class.'@putOne', ['model' => $parent, 'children' => $childModel]);

        $this->getRelation($parent)->save($childModel);

        // Parent should be reindexed itself
        $searchIndexer->reindexOne($parent, []);

        // Need to reindex childModel and all relations
        $searchIndexer->reindexOne($childModel);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->created()
            ->item($childModel);
    }

    /**
     * Put many entities.
     * Internally make use of Relation::sync().
     *
     * @param  Request $request
     * @param string $id
     * @return ApiResponse
     */
    public function putMany(Request $request, ElasticSearchIndexer $searchIndexer, $id)
    {
        $parent = $this->findParentEntity($id);

        $requestCollection = $request->json()->all();
        $this->validateRequestCollection($requestCollection, $this->getChildModel());

        $existingModels = $this->findChildrenCollection($requestCollection, $parent);

        $childModels = $this->fillModels($this->getChildModel(), $existingModels, $requestCollection);

        $this->checkPermission(static::class.'@putMany', ['model' => $parent, 'children' => $childModels]);

        // Collect all affected items
        $reindexItems = $searchIndexer->mergeUniqueCollection(
            $searchIndexer->getAllItemsFromRelations($parent, [$this->relationName]),
            $childModels
        );

        $relation = $this->getRelation($parent);

        if ($relation instanceof BelongsToMany) {
            $this->saveNewItemsInCollection($childModels);
            $relation->sync($this->makeSyncList($childModels, $requestCollection));
        } else {
            $relation->saveMany($childModels);
        }

        // We need to reindex all affected items with relations
        $searchIndexer->reindexMany($reindexItems);

        // Reindex parent without relations
        $searchIndexer->reindexOne($parent, []);

        $this->postSync($parent, $childModels);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->created()
            ->collection($childModels);
    }

    /**
     * Patch an entity.
     *
     * @param  Request $request
     * @param  string $id
     * @param bool|string $childId
     * @return ApiResponse
     */
    public function patchOne(Request $request, ElasticSearchIndexer $searchIndexer, $id, $childId = false)
    {
        $parent = $this->findParentEntity($id);

        //If the child id is not passed in the url, fall back to the child id being the parent id (for the case where the relationship is HasOne with primary key being foreign parent id)
        if ($this->childIdCanFallbackToParent($childId, $parent)) {
            $childId = $parent->getKey();
        } else {
            $this->checkEntityIdMatchesRoute($request, $childId, $this->getChildModel(), false);
        }

        $childModel = $this->findOrFailChildEntity($childId, $parent);

        $requestEntity = $request->json()->all();
        $this->validateRequest($requestEntity, $this->getValidationRules($id, $requestEntity), $childModel);

        $this->fillModel($childModel, $request->json()->all());

        $this->checkPermission(static::class.'@patchOne', ['model' => $parent, 'children' => $childModel]);

        $this->getRelation($parent)->save($childModel);

        // Parent should be reindexed itself
        $searchIndexer->reindexOne($parent, []);

        // Need to reindex childModel and all relations
        $searchIndexer->reindexOne($childModel);

        return $this->getResponse()->noContent();
    }

    /**
     * Patch many entites.
     *
     * @param string $id
     * @param  Request $request
     * @return ApiResponse
     */
    public function patchMany(Request $request, ElasticSearchIndexer $searchIndexer, $id)
    {
        $requestCollection = $request->json()->all();

        $this->validateRequestCollection($requestCollection, $this->getChildModel(), true);

        $parent = $this->findParentEntity($id);
        $existingModels = $this->findOrFailChildrenCollection($requestCollection, $parent);

        $childModels = $this->fillModels($this->getChildModel(), $existingModels, $requestCollection);

        $this->checkPermission(static::class.'@patchMany', ['model' => $parent, 'children' => $childModels]);

        $this->getRelation($parent)->saveMany($childModels);

        // Parent should be reindexed itself
        $searchIndexer->reindexOne($parent, []);

        // We need to reindex all items with relations
        $searchIndexer->reindexMany($childModels);

        return $this->getResponse()->noContent();
    }

    /**
     * Delete an entity.
     *
     * @param  string $id
     * @param bool|string $childId
     * @return ApiResponse
     * @throws \Exception
     */
    public function deleteOne($id, ElasticSearchIndexer $searchIndexer, $childId = false)
    {
        $parent = $this->findParentEntity($id);

        //If the child id is not passed in the url, fall back to the child id being the parent id (for the case where the relationship is HasOne with primary key being foreign parent id)
        if ($this->childIdCanFallbackToParent($childId, $parent)) {
            $childId = $parent->getKey();
        }

        $childModel = $this->findOrFailChildEntity($childId, $parent);

        $this->checkPermission(static::class.'@deleteOne', ['model' => $parent, 'children' => $childModel]);

        $searchIndexer->deleteOneAndReindexRelated($childModel);

        $parent->fireRevisionableEvent('deleteChild', [$childModel, $this->relationName]);

        return $this->getResponse()->noContent();
    }

    /**
     * Delete many entites.
     *
     * @param string $id
     * @param  Request  $request
     * @return ApiResponse
     */
    public function deleteMany(Request $request, ElasticSearchIndexer $searchIndexer, $id)
    {
        $requestCollection = $request->json()->all();
        $model = $this->findParentEntity($id);

        $childModels = $this->findOrFailChildrenCollection($requestCollection, $model);

        $this->checkPermission(static::class.'@deleteMany', ['model' => $model, 'children' => $childModels]);

        $searchIndexer->deleteManyAndReindexRelated($childModels);

        return $this->getResponse()->noContent();
    }

    /**
     * @param null $entityId
     * @param array $requestEntity
     * @return array
     */
    protected function getValidationRules($entityId = null, array $requestEntity = [])
    {
        $childRules = $this->getChildModel()->getValidationRules($entityId, $requestEntity);
        $pivotRules = $this->getPivotValidationRules($entityId, $requestEntity);

        return array_merge($childRules, $pivotRules);
    }

    /**
     * @param $childId
     * @param BaseModel $parentModel
     * @return bool
     */
    protected function childIdCanFallbackToParent($childId, BaseModel $parentModel)
    {
        $fk = $this->getRelation($parentModel)->getForeignKey();
        $parentKey = $parentModel->getKeyName();

        return $childId === false && ends_with($fk, $parentKey);
    }

    /**
     * Override this method to provide custom validation rules.
     *
     * @param null $entityId
     * @param array $requestEntity
     * @return array
     */
    protected function getPivotValidationRules($entityId = null, array $requestEntity = [])
    {
        return [];
    }
}
