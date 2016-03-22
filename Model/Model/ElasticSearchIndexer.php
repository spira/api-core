<?php

namespace Spira\Core\Model\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ElasticSearchIndexer
{
    protected $relations;

    function __construct(array $relations = [])
    {
        $this->relations = $relations;
    }

    public function reindexOne($model, array $relations = null)
    {
        if (!($model instanceof IndexedModel)) {
            return false;
        }

        $reindexRelations = $this->getRelationsForReindex($model);

        if (!is_null($relations)) {
            $reindexRelations = array_intersect($reindexRelations, $relations);
        }

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
        $reindexItems = new Collection();
        if ($model instanceof IndexedModel) {
            $relations = $this->getRelationsForReindex($model);

            foreach ($relations as $relation) {
                $reindexItems = $reindexItems->merge($this->getRelationItems($model, $relation));
            }
        }

        $model->delete();

        if (!$reindexItems->isEmpty()) {
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
        // @todo - getRelation() does not work as expected - need to refactor
        if (!$result = $model->getRelation($relation)->getResults()) {
            return [];
        }

        return is_array($result) || $result instanceof \Traversable ? $result : [$result];
    }
}