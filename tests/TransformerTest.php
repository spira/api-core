<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests;

use Illuminate\Database\Eloquent\Collection;
use Mockery as m;
use Spira\Core\Model\Test\SecondTestEntity;
use Spira\Core\Model\Test\TestEntity;
use Spira\Core\Responder\Transformers\EloquentModelTransformer;
use Spira\Core\Responder\TransformerService;

/**
 * @property EloquentModelTransformer transformer
 */
class TransformerTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->transformer = $this->app->make(EloquentModelTransformer::class);
        $this->app->get('test_entity_one', ['as' => TestEntity::class, 'uses' => 'One@one']);
        $this->app->get('test_entity_two', ['as' => SecondTestEntity::class, 'uses' => 'Two@two']);
    }

    /**
     * Testing BaseTransformer.
     */
    public function testSnakeCaseToCamelCase()
    {
        $data = m::mock('Illuminate\Contracts\Support\Arrayable');
        $data->shouldReceive('toArray')
            ->once()
            ->andReturn(['foo_bar' => 'foobar']);

        $transformed = $this->transformer->transformItem($data);
        $this->assertArrayHasKey('fooBar', $transformed);
        $this->assertArrayNotHasKey('_self', $transformed);
    }

    public function testItemNestedData()
    {
        $data = m::mock('Illuminate\Contracts\Support\Arrayable');
        $data->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'foo_bar' => 'foobar',
                'nested_data' => ['foo_bar' => true, 'foo' => true, 'bar_foo' => true],
            ]);

        $data = $this->transformer->transformItem($data);

        $this->assertArrayHasKey('fooBar', $data['nestedData']);
        $this->assertArrayHasKey('barFoo', $data['nestedData']);
        $this->assertArrayNotHasKey('foo_bar', $data['nestedData']);
    }

    /**
     * Testing Transformer Service.
     */
    public function testCollection()
    {
        $entities = factory(TestEntity::class, 3)->make();

        $collection = $this->transformer->transformCollection($entities);

        $this->assertCount(3, $collection);
        $this->checkItem($collection[0]);
    }

    public function testItem()
    {
        $entity = factory(TestEntity::class)->make();

        $item = $this->transformer->transformItem($entity);

        $this->checkItem($item);
    }

    public function testCastNullDatetime()
    {
        $entity = factory(TestEntity::class)->make([
            'time' => null
        ]);

        $item = $this->transformer->transformItem($entity);

        $this->assertEmpty($item['time']);
    }

    public function testTransfomerService()
    {
        $checkArray = ['item' => 'foo'];
        $transformed = $this->transformer->getService()->item(new Collection($checkArray));
        $this->assertEquals($checkArray, $transformed);
    }

    public function testNonArrayableItem()
    {
        $array = ['foo' => 'bar'];

        $item = $this->transformer->transformItem($array);

        $this->assertTrue(is_array($item));
    }

    public function testNullItem()
    {
        $item = $this->transformer->transformItem(null);

        $this->assertTrue(is_null($item));
    }

    public function testGetTransformer()
    {
        $service = $this->transformer->getService();
        $getTransformer = function () {
            return $this->getTransformer();
        };

        $getTransformer = $getTransformer->bindTo($service, TransformerService::class);
        $transformer = $getTransformer();
        $this->assertInstanceOf(\CLosure::class, $transformer);

        $this->assertEquals([1], $transformer(1));
    }

    public function testRelationSelf()
    {
        /** @var TestEntity $entity */
        $entity = factory(TestEntity::class)->create();
        $hasOneEntity = factory(SecondTestEntity::class)->make();
        $hasManyEntity = factory(SecondTestEntity::class, 2)->make()->all();

        $entity->testOne()->save($hasOneEntity);
        $entity->testMany()->saveMany($hasManyEntity);

        $check = $entity->testOne;
        $check = $entity->testMany;

        $data = $this->transformer->transformItem($entity);

        $this->assertArrayHasKey('_self', $data);
        $this->assertArrayHasKey('_testOne', $data);
        $this->assertArrayHasKey('_testMany', $data);
        $this->assertArrayHasKey('_self', $data['_testOne']);
        foreach ($data['_testMany'] as $value) {
            $this->assertArrayHasKey('_self', $value);
        }
    }

    protected function checkItem($item)
    {
        $this->assertTrue(is_array($item));

        $this->assertArrayHasKey('date', $item);
        $date = \DateTime::createFromFormat('Y-m-d', $item['date']);
        $this->assertNotEmpty($date, 'Date is short formatted');

        $this->assertArrayHasKey('time', $item);
        $date = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $item['time']); // Format "c" does not working
        $this->assertNotEmpty($date, 'Time is ISO formatted');
    }
}
