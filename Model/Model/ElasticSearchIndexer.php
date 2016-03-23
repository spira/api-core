<?php

namespace Spira\Core\Model\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class ElasticSearchIndexer
{
    protected $relations;

    public function __construct(array $relations = [])
    {
        $this->relations = $relations;
    }

    public function reindexOne($model, array $relations = null)
    {
        if (! ($model instanceof IndexedModel)) {
            return false;
        }

        $reindexRelations = $this->filterRelations($model, $relations);
        foreach ($reindexRelations as $relation) {
            $this->reindexMany($this->getRelationItems($model, $relation), []);
        }

        return $model->updateIndex();
    }

    /**
     * @param IndexedModel[]|array|\Traversable $array
     */
    public function reindexMany($array, array $relations = null)
    {
        foreach ($array as $item) {
            $this->reindexOne($item, $relations);
        }
    }

    public function deleteOneAndReindexRelated(Model $model)
    {
        $reindexItems = $this->getAllItemsFromRelations($model);

        $model->delete();

        if (! $reindexItems->isEmpty()) {
            $this->reindexMany($reindexItems, []);
        }
    }

    /**
     * @param Model[] $array
     */
    public function deleteManyAndReindexRelated($array)
    {
        foreach ($array as $model) {
            $this->deleteOneAndReindexRelated($model);
        }
    }

    /**
     * Returns items from all relations for reindexing if model is IndexedModel.
     *
     * @return Collection
     * @throws \Exception
     */
    public function getAllItemsFromRelations(Model $model, array $relations = null)
    {
        $reindexItems = new Collection();

        if ($model instanceof IndexedModel) {
            $fetchRelations = $this->filterRelations($model, $relations);

            foreach ($fetchRelations as $relation) {
                $reindexItems = $reindexItems->merge($this->getRelationItems($model, $relation));
            }
        }

        return $reindexItems;
    }

    /**
     * Merges items to collection and preserves uniqueness by Model::getKey().
     *
     * @return Collection
     */
    public function mergeUniqueCollection(Collection $collection, $items)
    {
        return $collection->merge($items)
            ->unique(function (Model $model) {
                return $model->getKey();
            });
    }

    protected function getRelationsForReindex(IndexedModel $model)
    {
        $class = $model->getMorphClass();

        return isset($this->relations[$class]) ? $this->relations[$class] : [];
    }

    /**
     * @return array|\Traversable
     */
    protected function getRelationItems(IndexedModel $model, $relation)
    {
        if (! method_exists($model, $relation)) {
            throw new \Exception(sprintf('Tried to reindex unexistant relation "%s" on model "%s"', $relation, $model->getMorphClass()));
        }

        /** @var Relation $rel */
        $rel = call_user_func([$model, $relation]);
        if (! $result = $rel->getResults()) {
            return [];
        }

        return is_array($result) || $result instanceof \Traversable ? $result : [$result];
    }

    protected function filterRelations(Model $model, array $relations = null)
    {
        $reindexRelations = $this->getRelationsForReindex($model);

        if (! is_null($relations)) {
            $reindexRelations = array_intersect($reindexRelations, $relations);
        }

        return $reindexRelations;
    }
}
