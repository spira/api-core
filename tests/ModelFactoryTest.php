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

    public function testCustom()
    {
        $entity = $this->getFactory(TestEntity::class, 'custom')->make();
        $this->assertEquals('custom', $entity->varchar);
    }

    public function testAllBasic()
    {
        $entity = $this->getFactory(TestEntity::class)->json();
        $this->assertJson($entity);
        $entityModel = $this->getFactory(TestEntity::class)->make();
        $this->assertUuid($entityModel->entity_id);

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

        $collection = $this->getFactory(TestEntity::class)->setModel($entityModel)->count(3)->make();

        foreach ($collection as $item) {
            $this->assertInstanceOf(TestEntity::class, $item);
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
