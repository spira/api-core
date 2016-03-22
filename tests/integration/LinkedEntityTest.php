<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests\integration;

use Spira\Core\tests\TestCase;
use Spira\Core\Model\Test\TestEntity;
use Spira\Core\Model\Test\SecondTestEntity;
use Spira\Core\tests\Extensions\ElasticSearchIndexerTrait;

/**
 * Class LinkedEntityTest.
 * @group integration
 */
class LinkedEntityTest extends TestCase
{
    use ElasticSearchIndexerTrait;

    public function setUp()
    {
        parent::setUp();

        $this->app->group([], function ($app) {
            require __DIR__.'/test_routes.php';
        });
    }

    public function testGetAll()
    {
        $entity = $this->makeEntity();
        $ids = $entity->secondTestEntities()->get()->pluck('entity_id')->toArray();

        $this->getJson('test/many/'.$entity->entity_id.'/children');

        $response = $this->getJsonResponseAsArray();

        $this->assertArrayEquals($ids, $response, 'entityId');
    }

    public function testAttachOne()
    {
        /** @var $entity TestEntity */
        $entity = $this->getFactory(TestEntity::class)->create();
        $factory = $this->getFactory(SecondTestEntity::class);
        $second = $factory->create();

        $transformed = $factory->transformed();
        $transformed['value'] = 'ololo';

        $this->expectElasticSearchReindexOne($entity, ['secondTestEntities']);

        $this->putJson('test/many/'.$entity->entity_id.'/children/'.$second->entity_id, $transformed);

        $this->assertResponseStatus(201);
        $this->assertResponseHasNoContent();

        $comparedEntity = $entity->secondTestEntities()->first();
        $this->assertEquals($second->entity_id, $comparedEntity->entity_id);
        $this->assertEquals('ololo', $comparedEntity->value);
    }

    public function testAttachOneWithoutRequestEntity()
    {
        /** @var $entity TestEntity */
        $entity = $this->getFactory(TestEntity::class)->create();
        $second = $this->getFactory(SecondTestEntity::class)->create();

        $this->expectElasticSearchReindexOne($entity, ['secondTestEntities']);

        $this->putJson('test/many/'.$entity->getKey().'/children/'.$second->getKey());

        $this->assertResponseStatus(201);
        $this->assertResponseHasNoContent();

        $this->assertEquals($entity->secondTestEntities()->first()->entity_id, $second->entity_id);
    }

    public function testAttachMany()
    {
        $entity = $this->makeEntity();
        $factory = $this->getFactory(SecondTestEntity::class);
        $newEntities = $factory->count(3)->create();

        $ids = array_merge(
            $entity->secondTestEntities()->get()->pluck('entity_id')->toArray(),
            $newEntities->pluck('entity_id')->toArray()
        );

        $this->expectElasticSearchReindexOne($entity, ['secondTestEntities']);

        $this->postJson('test/many/'.$entity->entity_id.'/children', $factory->transformed());

        $this->assertResponseStatus(201);
        $this->assertArrayEquals($ids, $entity->secondTestEntities()->get()->pluck('entity_id')->toArray());
    }

    public function testSyncMany()
    {
        $entity = $this->makeEntity();
        $factory = $this->getFactory(SecondTestEntity::class);
        $factory->count(3)->create();

        $transformed = $factory->transformed();
        $transformed[] = $this->getFactory(SecondTestEntity::class)
            ->setModel($entity->secondTestEntities()->first())
            ->transformed();

        $this->expectElasticSearchReindexOne($entity, ['secondTestEntities']);

        $this->putJson('test/many/'.$entity->entity_id.'/children', $transformed);

        $this->assertResponseStatus(201);

        $this->assertCount(4, array_pluck($this->getJsonResponseAsArray(), '_self'));

        $this->assertArrayEquals(
            $entity->secondTestEntities()->get()->pluck('entity_id')->toArray(),
            $transformed,
            'entityId'
        );
    }

    public function testDetachOne()
    {
        $entity = $this->makeEntity();
        $second = $entity->secondTestEntities()->first();

        // @todo fix conflict
        //$mock = $this->mockElasticSearchIndexer();
        //$mock->shouldReceive('reindexOne')->once()->with($this->makeElasticSearchOneExpectation($entity), []);
        //$mock->shouldReceive('reindexOne')->once()->with($this->makeElasticSearchOneExpectation($second), []);

        $this->deleteJson('test/many/'.$entity->entity_id.'/children/'.$second->entity_id);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();

        $this->assertNotContains(
            $second->entity_id,
            $entity->secondTestEntities()->get()->pluck('entity_id')->toArray()
        );

        // If entity does not attached it doesn't throws an error
        $this->deleteJson('test/many/'.$entity->entity_id.'/children/'.$second->entity_id);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();
    }

    public function testDetachMany()
    {
        $entity = $this->makeEntity();

        $this->expectElasticSearchReindexOne($entity, []);

        $this->deleteJson('test/many/'.$entity->entity_id.'/children');

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();

        $this->assertTrue($entity->secondTestEntities()->get()->isEmpty());
    }

    /**
     * Generates TestEntity with 3 SecondEntites.
     * First of SecondEntites has 3 TestEntities, others has 1.
     *
     * @return TestEntity
     */
    protected function makeEntity()
    {
        $firstEntities = $this->getFactory(TestEntity::class)->count(5)->create();
        $secondEntities = $this->getFactory(SecondTestEntity::class)->count(5)->create();

        $firstIds = $firstEntities->take(3)->pluck('entity_id')->toArray();
        $secondIds = $secondEntities->take(3)->pluck('entity_id')->toArray();

        $first = $firstEntities->first();

        $first->secondTestEntities()->attach($secondIds);
        $first->secondTestEntities()->first()->testEntities()->sync($firstIds);

        return $first;
    }
}
