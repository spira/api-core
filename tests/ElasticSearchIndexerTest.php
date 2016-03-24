<?php

namespace Spira\Core\tests;

use Mockery\Mock;
use Spira\Core\Model\Test\TestEntity;
use Illuminate\Database\Eloquent\Model;
use Spira\Core\Model\Model\IndexedModel;
use Spira\Core\Model\Model\ElasticSearchIndexer;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Class ElasticSearchIndexerTest.
 * @group elasticsearch
 */
class ElasticSearchIndexerTest extends TestCase
{
    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Tried to reindex non-existant relation "badRelation" on model "Spira\Core\Model\Model\IndexedModel"
     */
    public function testUnexistantRelationError()
    {
        $esi = $this->makeIndexer();
        $entity = $this->makeEntityMock(IndexedModel::class);

        $esi->reindexOne($entity);
    }

    public function testReindexOneWithAllRelations()
    {
        $esi = $this->makeIndexer();

        /** @var IndexedModel $related */
        $related = \Mockery::mock(IndexedModel::class)->makePartial();
        $related->shouldReceive('updateIndex')->twice();

        $relation = \Mockery::mock(Relation::class)->makePartial();
        $relation->shouldReceive('getResults')->once()->andReturn([$related]); // Relation returns collection
        $relation->shouldReceive('getResults')->once()->andReturn($related); // Relation returns one item

        $entity = $this->makeEntityMock();

        $entity->shouldReceive('updateIndex')->once()->andReturn([]);
        $entity->shouldReceive('secondTestEntities')->once()->andReturn($relation);
        $entity->shouldReceive('testMany')->once()->andReturn($relation);

        $this->assertIsArray($esi->reindexOne($entity)); // Assert method returns result of $entity->updateIndex()
    }

    public function testReindexOneWithSpecifiedRelations()
    {
        $esi = $this->makeIndexer();
        $entity = $this->makeEntityMock();

        $relation = \Mockery::mock(Relation::class)->makePartial();
        $relation->shouldReceive('getResults')->once()->andReturn([]);

        $entity->shouldReceive('updateIndex')->once();
        $entity->shouldReceive('secondTestEntities')->once()->andReturn($relation);

        $esi->reindexOne($entity, ['secondTestEntities', 'nonexistantRelation']);
    }

    public function testReindexOneWithDisabledRelations()
    {
        $esi = $this->makeIndexer();

        $entity = $this->makeEntityMock();
        $entity->shouldReceive('updateIndex')->once();

        $esi->reindexOne($entity, []);
    }

    public function testReindexOneNotIndexedModel()
    {
        $esi = $this->makeIndexer();

        $this->assertFalse($esi->reindexOne('bad'));
    }

    public function testReindexOneNotInConfig()
    {
        $esi = new ElasticSearchIndexer([]);

        $entity = $this->makeEntityMock();
        $entity->shouldReceive('updateIndex')->once();

        $esi->reindexOne($entity);
    }

    public function testReindexManyWithRelations()
    {
        $entity = 'does not matter';

        /** @var Mock|ElasticSearchIndexer $esi */
        $esi = \Mockery::mock(ElasticSearchIndexer::class)->makePartial();
        $esi->shouldReceive('reindexOne')->twice()->with($entity, null);

        $esi->reindexMany([$entity, $entity]);
    }

    public function testReindexManyWithoutRelations()
    {
        $entity = 'does not matter';

        /** @var Mock|ElasticSearchIndexer $esi */
        $esi = \Mockery::mock(ElasticSearchIndexer::class)->makePartial();
        $esi->shouldReceive('reindexOne')->twice()->with($entity, []);

        $esi->reindexMany([$entity, $entity], []);
    }

    public function testDeleteOne()
    {
        $esi = $this->makeIndexer();

        $related = $this->makeEntityMock();
        $related->shouldReceive('updateIndex')->once();

        $relation = \Mockery::mock(Relation::class);
        $relation->shouldReceive('getResults')->once()->andReturn([$related]);
        $relation->shouldReceive('getResults')->once()->andReturnNull();

        $entity = $this->makeEntityMock();
        $entity->shouldReceive('delete')->once();
        $entity->shouldReceive('secondTestEntities')->once()->andReturn($relation);
        $entity->shouldReceive('testMany')->once()->andReturn($relation);

        $esi->deleteOneAndReindexRelated($entity);
    }

    public function testDeleteOneNonIndexedModel()
    {
        $esi = $this->makeIndexer();

        $entity = \Mockery::mock(Model::class);
        $entity->shouldReceive('delete')->once();

        $esi->deleteOneAndReindexRelated($entity);
    }

    public function testDeleteMany()
    {
        $entity = \Mockery::mock(Model::class);

        /** @var Mock|ElasticSearchIndexer $esi */
        $esi = \Mockery::mock(ElasticSearchIndexer::class)->makePartial();
        $esi->shouldReceive('deleteOneAndReindexRelated')->twice()->with($entity);

        $esi->deleteManyAndReindexRelated([$entity, $entity]);
    }

    protected function makeIndexer()
    {
        return new ElasticSearchIndexer([
            TestEntity::class => ['secondTestEntities', 'testMany'],
            IndexedModel::class => ['badRelation'],
        ]);
    }

    /** @return \Mockery\Mock|TestEntity */
    protected function makeEntityMock($class = null)
    {
        $class = $class ?: TestEntity::class;
        $entity = \Mockery::mock($class)->makePartial();
        $entity->shouldReceive('getMorphClass')->andReturn($class);

        return $entity;
    }
}
