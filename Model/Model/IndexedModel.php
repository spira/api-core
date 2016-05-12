<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Model\Model;

use Elasticsearch\Client;
use Elasticquent\ElasticquentTrait;
use Illuminate\Database\Eloquent\Collection;
use Spira\Core\Model\Collection\IndexedCollection;

abstract class IndexedModel extends BaseModel
{
    use ElasticquentTrait;

    protected $indexRelations = [];

    protected $mappingProperties = [
        'title' => [
            'type' => 'string',
            'analyzer' => 'standard',
        ],
    ];

    public static function createCustomIndexes()
    {
    }

    public static function deleteCustomIndexes()
    {
    }

    /**
     * Create a new Eloquent Collection instance with ElasticquentCollectionTrait.
     *
     * @param  array $models
     * @return IndexedCollection
     */
    public function newCollection(array $models = [])
    {
        return new IndexedCollection($models, static::class);
    }

    /**
     * Check if index exists.
     * @param null $indexName
     * @return bool
     */
    public static function indexExists($indexName = null)
    {
        $instance = new static;

        if (! $indexName) {
            $indexName = $instance->getIndexName();
        }

        $params = [
            'index' => $indexName,
        ];

        return $instance->getElasticSearchClient()->indices()->exists($params);
    }

    public static function deleteIndex($indexName = null)
    {
        $instance = new static;

        if (! $indexName) {
            $indexName = $instance->getIndexName();
        }

        $index = [
            'index' => $indexName,
        ];

        return $instance->getElasticSearchClient()->indices()->delete($index);
    }

    /**
     * Remove all of this entity from the index.
     * @return bool
     */
    public static function removeAllFromIndex()
    {
        return self::mappingExists() && self::deleteMapping();
    }

    /**
     * Get the count of this entity in the index.
     * @return mixed
     */
    public function countIndex()
    {
        $instance = new static;

        $params = [
            'index' => $instance->getIndexName(),
        ];

        return $instance->getElasticSearchClient()->count($params);
    }

    /**
     * Get entity data which is to be entered into search index.
     *
     * Note: Every entity, including it's nested entities, requires mappingProperties to be defined in it's model
     * or it will not be indexed correctly.
     *
     * @return array
     */
    public function getIndexDocumentData()
    {
        $relations = [];

        if (! empty($this->indexRelations)) {
            // We have to do this because we don't know if the relation is one to or one to many. If it is one to one
            // we don't want to strip out the keys.
            foreach ($this->indexRelations as $nestedModelName) {
                /** @var IndexedModel|IndexedCollection $results */
                $results = $this->$nestedModelName()->getResults();

                if (is_null($results)) {
                    break;
                } elseif ($results instanceof Collection) {
                    $nestedData = $results->map(function (IndexedModel $result) {
                        return array_intersect_key($result->attributesToArray(), $result->getMappingProperties());
                    });

                    $relations['_'.$nestedModelName] = $nestedData;
                } else {
                    $relations['_'.$nestedModelName] = array_intersect_key($results->attributesToArray(), $results->getMappingProperties());
                }
            }
        }

        // Only include attributes present in mappingProperties
        $attributes = array_intersect_key($this->attributesToArray(), $this->getMappingProperties());

        return array_merge($attributes, $relations);
    }

    /**
     * Return Elasticsearch client.
     *
     * @return Client
     */
    public function getElasticSearchClient()
    {
        return app(Client::class);
    }

    protected static function boot()
    {
        parent::boot(); //register the parent event handlers first

        static::created(
            function (IndexedModel $model) {
                $model->addToIndex();

                return true;
            }, PHP_INT_MAX
        );

        static::deleted(
            function (IndexedModel $model) {
                $model->removeFromIndex();

                return true;
            }, PHP_INT_MAX
        );

        static::updated(
            function (IndexedModel $model) {
                $model->updateIndex();

                return true;
            }, PHP_INT_MAX
        );
    }
}
