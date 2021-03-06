<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Controllers;

use Elasticquent\ElasticquentResultCollection;
use Elasticquent\ElasticquentTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spira\Core\Contract\Exception\BadRequestException;
use Spira\Core\Model\Collection\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spira\Core\Model\Model\BaseModel;
use Spira\Core\Model\Model\ElasticSearchIndexer;
use Spira\Core\Responder\Contract\TransformerInterface;
use Spira\Core\Responder\Paginator\RangeRequest;
use Spira\Core\Responder\Response\ApiResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class EntityController extends ApiController
{
    use RequestValidationTrait;

    /**
     * @var BaseModel
     */
    protected $model;

    public function __construct(BaseModel $model, TransformerInterface $transformer)
    {
        $this->model = $model;
        parent::__construct($transformer);
    }

    /**
     * Get all entities.
     *
     * @param Request $request
     * @return ApiResponse
     */
    public function getAll(Request $request)
    {
        $collection = $this->getAllEntities();
        $collection = $this->getWithNested($collection, $request);
        $this->checkPermission(static::class.'@getAll', ['model' => $collection]);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->collection($collection);
    }

    public function searchPaginated(RangeRequest $rangeRequest)
    {
        return $this->makePaginated($rangeRequest, 'searchPaginated', true, false);
    }

    public function getAllPaginated(RangeRequest $rangeRequest)
    {
        return $this->makePaginated($rangeRequest, 'getAllPaginated');
    }

    /**
     * Get one entity.
     *
     * @param Request $request
     * @param  string $id
     * @return ApiResponse
     */
    public function getOne(Request $request, $id)
    {
        $model = $this->findOrFailEntity($id);
        $model = $this->getWithNested($model, $request);
        $this->checkPermission(static::class.'@getOne', ['model' => $model]);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->item($model);
    }

    /**
     * Get one, matching on all the route parameters.
     * @param Request $request
     * @return ApiResponse
     */
    public function getOneMatchingRouteParams(Request $request)
    {
        $model = $this->findOrFailCompoundEntity($request->route()[2]);
        $model = $this->getWithNested($model, $request);
        $this->checkPermission(static::class.'@getOne', ['model' => $model]);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->item($model);
    }

    /**
     * Get all, matching on all the route parameters.
     * @param Request $request
     * @return ApiResponse
     */
    public function getAllMatchingRouteParams(Request $request)
    {
        $collection = $this->getAllMatchingEntities($request->route()[2]);
        $collection = $this->getWithNested($collection, $request);
        $this->checkPermission(static::class.'@getAll', ['model' => $collection]);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->collection($collection);
    }

    /**
     * Post a new entity.
     *
     * @param  Request $request
     * @return ApiResponse
     */
    public function postOne(Request $request)
    {
        $model = $this->getModel()->newInstance();
        $requestEntity = $request->json()->all();
        $this->validateRequest(
            $requestEntity,
            $this->getValidationRules($this->getKeyFromRequestEntity($this->getModel(), $requestEntity), $requestEntity)
        );
        $this->fillModel($model, $requestEntity);
        $this->checkPermission(static::class.'@postOne', ['model' => $model]);
        $model->save();

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->created()
            ->item($model);
    }

    /**
     * Put an entity.
     *
     * @param  string   $id
     * @param  Request  $request
     * @return ApiResponse
     */
    public function putOne(Request $request, ElasticSearchIndexer $searchIndexer, $id)
    {
        $this->checkEntityIdMatchesRoute($request, $id, $this->getModel());
        $model = $this->findOrNewEntity($id);
        $exists = $model->exists;

        $requestEntity = $request->json()->all();
        $this->validateRequest($requestEntity, $this->getValidationRules($id, $requestEntity));

        $this->fillModel($model, $request->json()->all());
        $this->checkPermission(static::class.'@putOne', ['model' => $model]);
        $model->save();

        $exists && $searchIndexer->reindexOne($model);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->created()
            ->item($model);
    }

    /**
     * Put many entities.
     *
     * @param  Request  $request
     * @return ApiResponse
     */
    public function putMany(Request $request, ElasticSearchIndexer $searchIndexer)
    {
        $requestCollection = $request->json()->all();

        $this->validateRequestCollection($requestCollection, $this->getModel());
        $existingModels = $this->findCollection($requestCollection);

        $modelCollection = $this->fillModels($this->getModel(), $existingModels, $requestCollection);

        $this->checkPermission(static::class.'@putMany', ['model' => $modelCollection]);

        $modelCollection->each(function (BaseModel $model) {
            return $model->save();
        });

        $searchIndexer->reindexMany($modelCollection);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->created()
            ->collection($modelCollection);
    }

    /**
     * Patch an entity.
     *
     * @param  string   $id
     * @param  Request  $request
     * @return ApiResponse
     */
    public function patchOne(Request $request, ElasticSearchIndexer $searchIndexer, $id)
    {
        $this->checkEntityIdMatchesRoute($request, $id, $this->getModel(), false);

        $model = $this->findOrFailEntity($id);

        $requestEntity = $request->json()->all();
        $this->validateRequest($requestEntity, $this->getValidationRules($id, $requestEntity), $model);

        $this->fillModel($model, $request->json()->all());
        $this->checkPermission(static::class.'@patchOne', ['model' => $model]);
        $model->save();

        $searchIndexer->reindexOne($model);

        return $this->getResponse()->noContent();
    }

    /**
     * Patch many entites.
     *
     * @param  Request  $request
     * @return ApiResponse
     */
    public function patchMany(Request $request, ElasticSearchIndexer $searchIndexer)
    {
        $requestCollection = $request->json()->all();

        $this->validateRequestCollection($requestCollection, $this->getModel(), true);

        $existingModels = $this->findOrFailCollection($requestCollection);

        $modelsCollection = $this->fillModels($this->getModel(), $existingModels, $requestCollection);

        $this->checkPermission(static::class.'@patchMany', ['model' => $existingModels]);

        $modelsCollection->each(function (BaseModel $model) {
            return $model->save();
        });

        $searchIndexer->reindexMany($modelsCollection);

        return $this->getResponse()->noContent();
    }

    /**
     * Delete an entity.
     *
     * @param  string   $id
     * @return ApiResponse
     */
    public function deleteOne(ElasticSearchIndexer $searchIndexer, $id)
    {
        $entity = $this->findOrFailEntity($id);

        $this->checkPermission(static::class.'@deleteOne', ['model' => $entity]);

        $searchIndexer->deleteOneAndReindexRelated($entity);

        return $this->getResponse()->noContent();
    }

    /**
     * Delete many entites.
     *
     * @param  Request  $request
     * @return ApiResponse
     */
    public function deleteMany(Request $request, ElasticSearchIndexer $searchIndexer)
    {
        $requestCollection = $request->json()->all();

        $modelsCollection = $this->findOrFailCollection($requestCollection);

        $this->checkPermission(static::class.'@deleteMany', ['model' => $modelsCollection]);

        $searchIndexer->deleteManyAndReindexRelated($modelsCollection);

        return $this->getResponse()->noContent();
    }

    /**
     * Helper for paginated controller actions with restrictions of allowed methods for getting content: listing and\or search.
     *
     * @return ApiResponse
     */
    protected function makePaginated(RangeRequest $rangeRequest, $permission, $allow_search = true, $allow_listing = true)
    {
        $request = $rangeRequest->getRequest();

        $totalCount = $this->countEntities();
        $limit = $rangeRequest->getLimit($this->paginatorDefaultLimit, $this->paginatorMaxLimit);
        $offset = $rangeRequest->isGetLast() ? $totalCount - $limit : $rangeRequest->getOffset();

        if ($request->has('q')) {
            if (! $allow_search) {
                throw new BadRequestException('Search not allowed');
            }

            $collection = $this->searchAllEntities($request->query('q'), $limit, $offset, $totalCount);
        } else {
            if (! $allow_listing) {
                throw new BadRequestException('Items listing not allowed');
            }

            $collection = $this->getAllEntities($limit, $offset);
            $collection = $this->getWithNested($collection, $request);
        }

        $this->checkPermission(static::class.'@'.$permission, ['model' => $collection]);

        return $this->getResponse()
            ->transformer($this->getTransformer())
            ->paginatedCollection($collection, $offset, $totalCount);
    }

    /**
     * @param $id
     * @return BaseModel
     */
    protected function findOrNewEntity($id)
    {
        try {
            return $this->getModel()->findByIdentifier($id);
        } catch (ModelNotFoundException $e) {
            return $this->getModel()->newInstance();
        }
    }

    /**
     * @param $id
     * @return BaseModel
     */
    protected function findOrFailEntity($id)
    {
        try {
            return $this->getModel()->findByIdentifier($id);
        } catch (ModelNotFoundException $e) {
            throw $this->notFoundException($this->getModel()->getKeyName());
        }
    }

    /**
     * @param array $routeParams
     * @return BaseModel
     */
    protected function findOrFailCompoundEntity(array $routeParams)
    {
        try {
            return $this->getModel()->findByRouteParams($routeParams);
        } catch (ModelNotFoundException $e) {
            throw $this->notFoundException($this->getModel()->getKeyName());
        }
    }

    /**
     * @param array $routeParams
     * @param null $limit
     * @param null $offset
     * @return Collection
     */
    protected function getAllMatchingEntities(array $routeParams, $limit = null, $offset = null)
    {
        return $this->getModel()->whereAll($routeParams)->take($limit)->skip($offset)->get();
    }

    /**
     * @return int
     */
    protected function countEntities()
    {
        return $this->getModel()->count();
    }

    /**
     * @param null $limit
     * @param null $offset
     * @return Collection
     */
    protected function getAllEntities($limit = null, $offset = null)
    {
        return $this->getModel()->getDefaultOrder()->take($limit)->skip($offset)->get();
    }

    /**
     * @param $query
     * @param null $limit
     * @param null $offset
     * @param null $totalCount
     * @return ElasticquentResultCollection
     */
    protected function searchAllEntities($query, $limit = null, $offset = null, &$totalCount = null)
    {
        /* @var ElasticquentTrait $model */
        $model = $this->getModel();

        if (isset($query['percolate']) && $query['percolate']) {
            $searchResults = $this->percolatedSearch($query);
        } else {
            $params = $this->convertQueryToElasticsearchRequest($query, $limit, $offset);
            $searchResults = $model->complexSearch($params);
        }

        if ($searchResults instanceof ElasticquentResultCollection) {
            $totalHits = $searchResults->totalHits();
        } else {
            $totalHits = $searchResults->count();
        }

        if ($totalHits === 0) {
            throw new NotFoundHttpException(sprintf('No results found for model `%s`', get_class($model)));
        }

        if (isset($totalCount) && $totalHits < $totalCount) {
            $totalCount = $totalHits;
        }

        return $searchResults;
    }

    /**
     * @param $query
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    protected function percolatedSearch($query)
    {
        throw new BadRequestException('Percolated Search not available for this entity');
    }

    protected function convertQueryToElasticsearchRequest($query, $limit = null, $offset = null)
    {
        /* @var ElasticquentTrait $model */
        $model = $this->getModel();
        // Complex query
        if (is_array($query)) {
            $params = [
                'index' => $model->getIndexName(),
                'type' => $model->getTypeName(),
                'body' => $this->translateQuery($query),
            ];

            if (is_numeric($limit)) {
                $params = array_merge($params, ['size' => $limit]);
            }

            if (is_numeric($offset)) {
                $params = array_merge($params, ['from' => $offset]);
            }

        // Simple query
        } else {
            $params = $model->getBasicEsParams(true, true, true, $limit, $offset);
            $params['body']['query'] = [
                'match_phrase_prefix' => [
                    '_all' => $query,
                ],
            ];
        }

        return $this->customSearchConditions($params);
    }

    /**
     * Method for adding custom search conditions by overriding in controllers.
     * Should return array for elasticsearch request for complexSearch method.
     */
    protected function customSearchConditions($params)
    {
        return $params;
    }

    /**
     * Takes a query and translates it into a query that elastic search understands.
     *
     * Expect query to be in form, e.g.:
     * {
     *      _all: [ "stringA", "stringB" ],
     *      someField: [ "stringA" ],
     *      _nestedEntity: { key: [ "stringA", "stringB" ]
     * }
     *
     * Notes:
     * - Empty values will be removed from search completely.
     * - You must pass an array, even if it only contains 1 string.
     *
     * @param $query
     * @return mixed
     */
    private function translateQuery($query)
    {
        $processedQuery['query']['bool']['must'] = [];

        foreach ($query as $key => $value) {
            if (Str::startsWith($key, '_') && $key != '_all') { // Nested entity
                reset($value);
                $fieldKey = key($value);

                foreach ($value[$fieldKey] as $fieldValue) {
                    if (! empty($fieldValue)) {
                        $snakeKey = snake_case($key);
                        array_push($processedQuery['query']['bool']['must'],
                            [
                                'nested' => [
                                    'path' => $snakeKey,
                                    'query' => [
                                        'bool' => [
                                            'must' => [
                                                'match_phrase_prefix' => [
                                                    $snakeKey.'.'.snake_case($fieldKey) => $fieldValue,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ]
                        );
                    }
                }
            } else {
                foreach ($value as $matchValue) {
                    if (! empty($matchValue)) {
                        array_push($processedQuery['query']['bool']['must'], [
                            'match_phrase_prefix' => [
                                snake_case($key) => $matchValue,
                            ],
                        ]);
                    }
                }
            }
        }

        if (empty($processedQuery['query']['bool']['must'])) { // No search params have been supplied, match all
            return ['query' => ['match_all' => []]];
        }

        return $processedQuery;
    }

    /**
     * @param $requestCollection
     * @return Collection
     */
    protected function findOrFailCollection($requestCollection)
    {
        $ids = $this->getIds($requestCollection, $this->getModel()->getKeyName());

        if (empty($ids)) {
            throw $this->notFoundManyException($ids, $this->getModel()->newCollection(), $this->getModel()->getKeyName());
        }

        $models = $this->getModel()->findMany($ids);

        if ($models && count($ids) !== $models->count()) {
            throw $this->notFoundManyException($ids, $models, $this->getModel()->getKeyName());
        }

        return $models;
    }

    /**
     * @param $requestCollection
     * @return Collection
     */
    protected function findCollection($requestCollection)
    {
        $ids = $this->getIds($requestCollection, $this->getModel()->getKeyName());

        return $this->getModel()->findMany($ids); //if $ids is empty, findMany returns an empty collection
    }

    /**
     * @return BaseModel
     */
    protected function getModel()
    {
        return $this->model;
    }

    /**
     * @param null $entityKey
     * @param array $requestEntity
     * @return array
     */
    protected function getValidationRules($entityKey = null, array $requestEntity = [])
    {
        return $this->getModel()->getValidationRules($entityKey, $requestEntity);
    }
    
}
