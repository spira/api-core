<?php

namespace Spira\Core\tests\Extensions;

use Illuminate\Database\Eloquent\Model;
use Spira\Core\Model\Collection\Collection;
use Spira\Core\Model\Model\ElasticSearchIndexer;

trait ElasticSearchIndexerTrait
{
    /**
     * Make a mock of ElasticSearchIndexer and add to container
     *
     * @return \Mockery\Mock|ElasticSearchIndexer
     */
    protected function mockElasticSearchIndexer()
    {
        $mock = \Mockery::mock(ElasticSearchIndexer::class)->makePartial();

        $this->app->instance(ElasticSearchIndexer::class, $mock);

        return $mock;
    }

    /**
     * Make a mock of ElasticSearchIndexer and add expectation of reindexMany call with folowing $args arguments
     *
     * @return \Mockery\Mock|ElasticSearchIndexer
     */
    protected function expectElasticSearchReindexMany($number, ...$args)
    {
        array_unshift($args, $this->makeElasticSearchManyExpectation($number));

        $mock = $this->mockElasticSearchIndexer();
        $mock->shouldReceive('reindexMany')->once()->withArgs($args);

        return $mock;
    }

    /**
     * Make a mock of ElasticSearchIndexer and add expectation of deleteManyAndReindexRelated call
     * If $passthru is true then real delete call will occur
     *
     * @return \Mockery\Mock|ElasticSearchIndexer
     */
    protected function expectElasticSearchDeleteMany($number, $passthru = false)
    {
        $mock = $this->mockElasticSearchIndexer();

        $call = $mock->shouldReceive('deleteManyAndReindexRelated')->once()->with($this->makeElasticSearchManyExpectation($number));
        if ($passthru) {
            $call->passthru();
        }

        return $mock;
    }

    /**
     * Make a mock of ElasticSearchIndexer and add expectation of reindexOne call with folowing $args arguments
     *
     * @return \Mockery\Mock|ElasticSearchIndexer
     */
    protected function expectElasticSearchReindexOne(Model $model, ...$args)
    {
        array_unshift($args, $this->makeElasticSearchOneExpectation($model));

        $mock = $this->mockElasticSearchIndexer();
        $mock->shouldReceive('reindexOne')->once()->withArgs($args);

        return $mock;
    }

    /**
     * Make a mock of ElasticSearchIndexer and add expectation of deleteOneAndReindexRelated
     * If $passthru is true then real delete call will occur
     *
     * @return \Mockery\Mock|ElasticSearchIndexer
     */
    protected function expectElasticSearchDeleteOne(Model $model, $passthru = false)
    {
        $exp = $this->makeElasticSearchOneExpectation($model);

        $mock = $this->mockElasticSearchIndexer();

        $call = $mock->shouldReceive('deleteOneAndReindexRelated')->once()->with($exp);
        if ($passthru) {
            $call->passthru();
        }

        return $mock;
    }

    /**
     * Makes a Mockery expectation for one item
     *
     * @return \Mockery\Matcher\Closure
     */
    protected function makeElasticSearchOneExpectation(Model $model)
    {
        return \Mockery::on(
            function (Model $entity) use ($model) {
                return get_class($entity) == $model->getMorphClass() && $entity->getKey() == $model->getKey();
            }
        );
    }

    /**
     * Makes a Mockery expectation for many items
     *
     * @return \Mockery\Matcher\Closure
     */
    protected function makeElasticSearchManyExpectation($number)
    {
        return \Mockery::on(
            function ($coll) use ($number) {
                return $coll instanceof Collection && $coll->count() == $number;
            }
        );
    }
}