<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests\integration;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use Rhumsaa\Uuid\Uuid;
use Spira\Core\Controllers\ChildEntityController;
use Spira\Core\Model\Collection\Collection;
use Spira\Core\Model\Model\Localization;
use Spira\Core\Model\Test\SecondTestEntity;
use Spira\Core\Model\Test\TestEntity;
use Spira\Core\Responder\Transformers\EloquentModelTransformer;
use Spira\Core\Responder\TransformerService;
use Spira\Core\tests\Extensions\WithAuthorizationMockTrait;
use Spira\Core\tests\TestCase;

/**
 * Class ChildEntityTest.
 * @group integration
 */
class ChildEntityTest extends TestCase
{
    use  WithAuthorizationMockTrait;

    public function setUp()
    {
        parent::setUp();

        TestEntity::flushEventListeners();
        TestEntity::boot();
        // Workaround for model event firing.
        // The package Bosnadev\Database used for automatic UUID creation relies
        // on model events (creating) to generate the UUID.
        //
        // Laravel/Lumen currently doesn't fire repeated model events during
        // unit testing, see: https://github.com/laravel/framework/issues/1181
        SecondTestEntity::flushEventListeners();
        SecondTestEntity::boot();

        $this->app->group([], function ($app) {
            require __DIR__.'/test_routes.php';
        });
    }

    /**
     * Prepare a factory generated entity to be sent as input data.
     *
     * @param Arrayable $entity
     *
     * @return array
     */
    protected function prepareEntity($entity)
    {
        // We run the entity through the transformer to get the attributes named
        // as if they came from the frontend.
        $transformer = $this->app->make(EloquentModelTransformer::class);
        $entity = $transformer->transform($entity);

        return $entity;
    }

    /**
     * @param TestEntity $model
     */
    protected function addRelatedEntities(TestEntity $model)
    {
        $this->getFactory(SecondTestEntity::class)->count(5)->make()
            ->each(function (SecondTestEntity $entity) use ($model) {
                $model->testMany()->save($entity);
            });
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testMissingRelationName()
    {
        $transformerService = \App::make(TransformerService::class);
        $transformer = new EloquentModelTransformer($transformerService);

        new MockMissingRelationNameController(new TestEntity, $transformer);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidRelationName()
    {
        $transformerService = \App::make(TransformerService::class);
        $transformer = new EloquentModelTransformer($transformerService);

        new MockInvalidRelationNameController(new TestEntity, $transformer);
    }

    public function testGetAll()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $this->getJson('/test/entities/'.$entity->entity_id.'/children');

        $this->assertResponseOk();
        $this->shouldReturnJson();
        $this->assertJsonArray();
        $this->assertJsonMultipleEntries();
    }

    public function testGetOneNotFoundParent()
    {
        $this->getJson('/test/entities/'.Uuid::uuid4().'/child/'.Uuid::uuid4());
        $this->assertResponseStatus(422);
        $this->shouldReturnJson();
    }

    public function testGetOne()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = $entity->testMany->first();

        $this->getJson('/test/entities/'.$entity->entity_id.'/child/'.$childEntity->entity_id);
        $object = json_decode($this->response->getContent());

        $this->assertResponseOk();
        $this->shouldReturnJson();

        $this->assertIsObject($object, ['entityId']);
        $this->assertUuid($object->entityId);
        $this->assertTrue(strlen($object->entityId) === 36, 'UUID has 36 chars');
        $this->assertTrue(is_string($object->value), 'Varchar column type is text');

        $this->assertObjectMatchesEntity($object, $childEntity, ['entity_id', 'value']);
    }

    public function testGetOneFallBackToParent()
    {
        $entity = factory(TestEntity::class)->create();
        $entity2 = factory(SecondTestEntity::class)->make();

        $entity2->entity_id = $entity->entity_id;
        $entity2->value = 'random_value_impossible_to_be_generated_as_id';

        $entity->testMany()->save($entity2);

        $this->getJson('/test/entities/'.$entity->entity_id.'/child');
        $object = json_decode($this->response->getContent());
        $this->assertResponseOk();
        $this->shouldReturnJson();

        $this->assertIsObject($object, ['entityId']);
        $this->assertUuid($object->entityId);
        $this->assertTrue(strlen($object->entityId) === 36, 'UUID has 36 chars');
        $this->assertTrue(is_string($object->value), 'Varchar column type is text');

        $this->assertEquals($entity2->value, 'random_value_impossible_to_be_generated_as_id');
    }

    public function testPostOneValid()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = factory(SecondTestEntity::class)->make();

        $this->withAuthorization()->postJson('/test/entities/'.$entity->entity_id.'/child', $this->prepareEntity($childEntity));

        $this->shouldReturnJson();
        $this->assertResponseStatus(201);
    }

    public function testPostOneInvalid()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = factory(SecondTestEntity::class)->make();
        $childEntity = $this->prepareEntity($childEntity);
        unset($childEntity['value']);

        $this->withAuthorization()->postJson('/test/entities/'.$entity->entity_id.'/child', $childEntity);

        $object = json_decode($this->response->getContent());

        $this->shouldReturnJson();
        $this->assertResponseStatus(422);
        $this->assertObjectHasAttribute('value', $object->invalid);
        $this->assertEquals('The value field is required.', $object->invalid->value[0]->message);
    }

    public function testPutOneNew()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = factory(SecondTestEntity::class)->make();
        $childEntity = $this->prepareEntity($childEntity);

        $rowCount = TestEntity::find($entity->entity_id)->testMany->count();

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/child/'.$childEntity['entityId'], $childEntity);

        $object = json_decode($this->response->getContent());

        $this->assertResponseStatus(201);
        $this->assertEquals($rowCount + 1, TestEntity::find($entity->entity_id)->testMany->count());
        $this->assertTrue(is_object($object));
    }

    public function testPutOneFallbackToParentId()
    {
        $entity = factory(TestEntity::class)->create();

        $entity2 = $this->getFactory(SecondTestEntity::class)->customize([
            'entity_id' => $entity->entity_id,
            'value' => 'random_value_impossible_to_be_generated_as_id',
        ])->transformed();

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/child', $entity2);

        $object = json_decode($this->response->getContent());

        $this->assertResponseStatus(201);
        $this->assertTrue(is_object($object));
    }

    public function testPutOneCollidingIds()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = $entity->testMany->first();
        $childEntity = $this->prepareEntity($childEntity);
        $prevId = $childEntity['entityId'];
        $childEntity['entityId'] = (string) Uuid::uuid4();

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/child/'.$prevId, $childEntity);

        $object = json_decode($this->response->getContent());

        $this->assertResponseStatus(400);
        $this->assertTrue(is_object($object));

        $this->assertObjectHasAttribute('message', $object);
        $this->assertEquals('Provided entity body does not match route parameter. The entity key cannot be updated', $object->message);
    }

    public function testPutOneNewInvalidId()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = factory(SecondTestEntity::class)->make();
        $childEntity = $this->prepareEntity($childEntity);
        $childEntity['entityId'] = 'foobar';

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/child/'.$childEntity['entityId'], $childEntity);

        $object = json_decode($this->response->getContent());
        $this->shouldReturnJson();
        $this->assertResponseStatus(500);
    }

    public function testPutManyNoIds()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = factory(SecondTestEntity::class, 5)->make();
        $childEntities = array_map(function ($entity) {
            return $this->prepareEntity($entity);
        }, $childEntities->all());
        foreach ($childEntities as &$childEntity) {
            unset($childEntity['entityId']);
            unset($childEntity['_self']);
        }

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/children', $childEntities);
        $this->shouldReturnJson();
        $this->assertResponseStatus(422);
    }

    public function testPatchManyNoIds()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = $entity->testMany;
        $data = array_map(function ($entity) {
            return [
                'value'   => 'foobar',
            ];
        }, $childEntities->all());

        $this->withAuthorization()->patchJson('/test/entities/'.$entity->entity_id.'/children', $data);
        $this->assertResponseStatus(422);
    }

    public function testPutManyNew()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = factory(SecondTestEntity::class, 5)->make();
        $childEntities = array_map(function ($entity) {
            return $this->prepareEntity($entity);
        }, $childEntities->all());

        $childCount = TestEntity::find($entity->entity_id)->testMany->count();

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/children', $childEntities);

        $object = json_decode($this->response->getContent());

        $this->assertResponseStatus(201);
        $this->assertEquals($childCount + 5, TestEntity::find($entity->entity_id)->testMany->count());
        $this->assertTrue(is_array($object));
        $this->assertCount(5, $object);
    }

    public function testPutManyNewBelongs()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = factory(SecondTestEntity::class, 5)->make();
        $childEntities = array_map(function ($entity) {
            return $this->prepareEntity($entity);
        }, $childEntities->all());

        $childCount = TestEntity::find($entity->entity_id)->secondTestEntities->count();

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/childrenbelongs', $childEntities);

        $object = json_decode($this->response->getContent());

        $this->assertResponseStatus(201);
        $this->assertEquals($childCount + 5, TestEntity::find($entity->entity_id)->secondTestEntities->count());
        $this->assertTrue(is_array($object));
        $this->assertCount(5, $object);
    }

    public function testPutManyNewInvalidId()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = factory(SecondTestEntity::class, 5)->make();
        $childEntities = array_map(function ($entity) {
            return array_add($this->prepareEntity($entity), 'entity_id', 'foobar');
        }, $childEntities->all());

        $childCount = TestEntity::find($entity->entity_id)->testMany->count();

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/children', $childEntities);

        $object = json_decode($this->response->getContent());

        $this->assertCount(5, $object->invalid);
        $this->assertObjectHasAttribute('entityId', $object->invalid[0]);
        $this->assertEquals('The entity id must be an UUID string.', $object->invalid[0]->entityId[0]->message);
        $this->assertEquals($childCount, TestEntity::find($entity->entity_id)->testMany->count());
    }

    public function testPutManyNewInvalid()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = factory(SecondTestEntity::class, 5)->make();
        $childEntities = array_map(function ($entity) {
            return $this->prepareEntity($entity);
        }, $childEntities->all());

        foreach ($childEntities as &$childEntity) {
            unset($childEntity['value']);
        }

        $rowCount = TestEntity::count();

        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/children', $childEntities);

        $object = json_decode($this->response->getContent());
        $this->assertCount(5, $object->invalid);
        $this->assertObjectHasAttribute('value', $object->invalid[0]);
        $this->assertEquals($rowCount, TestEntity::count());
    }

    public function testPatchOne()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = $entity->testMany->first();

        $this->withAuthorization()->patchJson('/test/entities/'.$entity->entity_id.'/child/'.$childEntity->entity_id, ['value' => 'foobar']);

        $entity = TestEntity::find($entity->entity_id);
        /** @var Collection $childEntities */
        $childEntities = $entity->testMany;
        $childEntity = $childEntities->find($childEntity->entity_id);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();
        $this->assertEquals('foobar', $childEntity->value);
    }

    public function testPatchOneFallbackToParentId()
    {
        $entity = factory(TestEntity::class)->create();
        $entity2 = $this->getFactory(SecondTestEntity::class)->customize([
            'entity_id' => $entity->entity_id,
            'value' => 'random_value_impossible_to_be_generated_as_id',
        ])->make();

        $entity->testMany()->save($entity2);

        $this->withAuthorization()->patchJson('/test/entities/'.$entity->entity_id.'/child', ['value' => 'foobar']);

        $entity = TestEntity::find($entity->entity_id);
        /** @var Collection $childEntities */
        $childEntities = $entity->testMany;
        $childEntity = $childEntities->find($entity2->entity_id);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();
        $this->assertEquals('foobar', $childEntity->value);
    }

    public function testPatchOneInvalidId()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $this->withAuthorization()->patchJson('/test/entities/'.$entity->entity_id.'/child/'.(string) Uuid::uuid4(), ['varchar' => 'foobar']);
        $object = json_decode($this->response->getContent());
        $this->assertObjectHasAttribute('entityId', $object->invalid);
        $this->assertEquals('The selected entity id is invalid.', $object->invalid->entityId[0]->message);
    }

    public function testPatchMany()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = $entity->testMany;
        $data = array_map(function ($entity) {
            return [
                'entityId' => $entity->entity_id,
                'value'   => 'foobar',
            ];
        }, $childEntities->all());

        $this->withAuthorization()->patchJson('/test/entities/'.$entity->entity_id.'/children', $data);

        $entity = TestEntity::find($entity->entity_id);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();
        foreach ($entity->testMany as $childEntity) {
            $this->assertEquals('foobar', $childEntity->value);
        }
    }

    public function testPatchManyInvalidId()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = $entity->testMany;
        $data = array_map(function ($entity) {
            return [
                'entityId' => (string) Uuid::uuid4(),
                'value'   => 'foobar',
            ];
        }, $childEntities->all());

        $this->withAuthorization()->patchJson('/test/entities/'.$entity->entity_id.'/children', $data);
        $object = json_decode($this->response->getContent());

        $this->assertObjectHasAttribute('entityId', $object->invalid[0]);
        $this->assertEquals('The selected entity id is invalid.', $object->invalid[0]->entityId[0]->message);
    }

    public function testPatchManyNewInvalid()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);

        $childEntities = $entity->testMany;
        $data = array_map(function ($entity) {
            return [
                'entityId' => $entity->entity_id,
                'value'   => null,
            ];
        }, $childEntities->all());

        $this->withAuthorization()->patchJson('/test/entities/'.$entity->entity_id.'/children', $data);
        $object = json_decode($this->response->getContent());

        $this->assertCount(5, $object->invalid);
        $this->assertObjectHasAttribute('value', $object->invalid[0]);
    }

    public function testDeleteOne()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = $entity->testMany->first();
        $childCount = TestEntity::find($entity->entity_id)->testMany->count();

        $this->withAuthorization()->deleteJson('/test/entities/'.$entity->entity_id.'/child/'.$childEntity->entity_id);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();
        $this->assertEquals($childCount - 1, TestEntity::find($entity->entity_id)->testMany->count());
    }

    public function testDeleteOneFallbackToParentId()
    {
        $entity = factory(TestEntity::class)->create();
        $entity2 = $this->getFactory(SecondTestEntity::class)->customize([
            'entity_id' => $entity->entity_id,
            'value' => 'random_value_impossible_to_be_generated_as_id',
        ])->make();

        $entity->testMany()->save($entity2);

        $this->withAuthorization()->deleteJson('/test/entities/'.$entity->entity_id.'/child');

        $entity = TestEntity::find($entity->entity_id);
        /** @var Collection $childEntities */
        $childEntities = $entity->testMany;
        $childEntity = $childEntities->find($entity2->entity_id);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();
        $this->assertEmpty($childEntity);
    }

    public function testDeleteOneInvalidId()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childCount = TestEntity::find($entity->entity_id)->testMany->count();

        $this->withAuthorization()->deleteJson('/test/entities/'.$entity->entity_id.'/child/'.(string) Uuid::uuid4());

        $object = json_decode($this->response->getContent());

        $this->assertObjectHasAttribute('entityId', $object->invalid);
        $this->assertEquals('The selected entity id is invalid.', $object->invalid->entityId[0]->message);
        $this->assertEquals($childCount, TestEntity::find($entity->entity_id)->testMany->count());
    }

    public function testDeleteMany()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childCount = TestEntity::find($entity->entity_id)->testMany->count();

        $childEntities = $entity->testMany;
        $data = array_map(function ($entity) {
            return [
                'entityId' => $entity->entity_id,
                'value'   => 'foobar',
            ];
        }, $childEntities->all());

        $this->withAuthorization()->deleteJson('/test/entities/'.$entity->entity_id.'/children', $data);

        $this->assertResponseStatus(204);
        $this->assertResponseHasNoContent();
        $this->assertEquals($childCount - 5, TestEntity::find($entity->entity_id)->testMany->count());
    }

    public function testDeleteManyInvalidId()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childCount = TestEntity::find($entity->entity_id)->testMany->count();
        $childEntities = $entity->testMany;

        $childEntities->first()->entity_id = (string) Uuid::uuid4();
        $childEntities->last()->entity_id = (string) Uuid::uuid4();

        $data = array_map(function ($entity) {
            return [
                'entityId' => $entity->entity_id,
                'value'   => 'foobar',
            ];
        }, $childEntities->all());

        $this->withAuthorization()->deleteJson('/test/entities/'.$entity->entity_id.'/children', $data);

        $object = json_decode($this->response->getContent());

        $this->assertTrue(is_array($object->invalid));
        $this->assertObjectHasAttribute('entityId', $object->invalid[0]);
        $this->assertNull($object->invalid[1]);
        $this->assertObjectHasAttribute('entityId', $object->invalid[4]);
        $this->assertEquals('The selected entity id is invalid.', $object->invalid[0]->entityId[0]->message);
        $this->assertEquals($childCount, TestEntity::find($entity->entity_id)->testMany->count());
    }

    /**
     * @group localizations
     */
    public function testPutOneChildLocalization()
    {
        $entity = factory(TestEntity::class)->create();
        $this->addRelatedEntities($entity);
        $childEntity = $entity->testMany->first();

        $supportedRegions = array_pluck(config('regions.supported'), 'code');
        $region = array_pop($supportedRegions);

        $localization = [
            'value' => 'foobar',
        ];

        // Give entity a localization
        $this->withAuthorization()->putJson('/test/entities/'.$entity->entity_id.'/child/'.$childEntity->entity_id.'/localizations/'.$region, $localization);
        $this->assertResponseStatus(201);

        // Get the saved localization
        $localizationModel = $childEntity->localizations->where('region_code', $region)->first();

        $savedLocalization = $localizationModel->localizations;

        // Ensure localization was saved correctly
        $this->assertEquals($localization['value'], $savedLocalization['value']);

        // Assert the cache
        $cachedLocalization = Localization::getFromCache($localizationModel->localizable_id, $localizationModel->region_code);

        $this->assertEquals($localization, $cachedLocalization);
    }
}

class MockMissingRelationNameController extends ChildEntityController
{
}

class MockInvalidRelationNameController extends ChildEntityController
{
    protected $relationName = 'noSuchRelation';
}
