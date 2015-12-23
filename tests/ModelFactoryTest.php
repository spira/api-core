<?php

namespace Spira\Core\tests;

use Illuminate\Support\Collection;
use Spira\Core\Model\Model\ModelFactory;
use Spira\Core\Model\Test\TestEntity;
use Spira\Core\Responder\Transformers\EloquentModelTransformer;

class ModelFactoryTest extends TestCase
{
    public function testException()
    {
        $this->setExpectedException('\LogicException', 'No factory class passed to model factory, cannot generate a mock');
        $this->getFactory()->make();
    }

    public function testAllBasic()
    {
        $entity = $this->getFactory(TestEntity::class)->json();
        $this->assertJson($entity);
        $entity = $this->getFactory(TestEntity::class)->make();
        $this->assertUuid($entity->entity_id);

        $factoryWithBasicEntity = $this->getFactory(TestEntity::class);
        $factoryWithBasicEntity->append('check', 'result');
        $factoryWithBasicEntity->showOnly(['entity_id']);
        $factoryWithBasicEntity->setTransformer(EloquentModelTransformer::class);
        $entity = $factoryWithBasicEntity->transformed();
        $this->assertUuid($entity['entityId']);
        $this->assertEquals($entity['check'], 'result');

        $factoryWithBasicEntity->append('check', 'result');
        $encodedEntity = $factoryWithBasicEntity->toJson();
        $this->assertEquals(json_decode($encodedEntity, JSON_OBJECT_AS_ARRAY)['entityId'], $entity['entityId']);

        $collection = $factoryWithBasicEntity->count(3)->make();
        foreach ($collection as $item) {
            $this->assertEquals($item->entity_id, $entity['entityId']);
        }

        $modelFactory = new ModelFactory();
        $entity = $modelFactory->make(TestEntity::class);
        $this->assertInstanceOf(TestEntity::class, $entity);

        $entity = $modelFactory->json(TestEntity::class);
        $this->assertJson($entity);
    }

    public function testAllCollection()
    {
        $testEntitty = $factory = $this->getFactory(TestEntity::class)->toArray();

        $factory = $this->getFactory(TestEntity::class);
        /** @var Collection $collection */
        $collection = $factory->count(10)->make();

        $collection->offsetSet(null, $testEntitty);

        $factory = $this->getFactory(TestEntity::class)->setCollection($collection);
    }
}
