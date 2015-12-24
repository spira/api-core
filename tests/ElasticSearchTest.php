<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests;

use Spira\Core\Model\Test\TestEntity;

/**
 * Class ElasticSearchTest.
 */
class ElasticSearchTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        if(!TestEntity::indexExists()){
            TestEntity::createIndex();
        }

        TestEntity::flushEventListeners();
        TestEntity::boot(); //run event listeners

        TestEntity::removeAllFromIndex();
    }

    public function testIndexExist()
    {
        $this->assertTrue(TestEntity::indexExists());
        $this->assertTrue((bool)TestEntity::deleteIndex());
        $this->assertFalse(TestEntity::indexExists());
    }

    public function testCountIndex()
    {
        /** @var TestEntity $entity */
        $entity = $this->getFactory(TestEntity::class)->create();

        $data = $entity->countIndex();
        $result = [
            "count" => 0,
            "_shards" =>
                [
                    "total" => 5,
                    "successful" => 5,
                    "failed" => 0
                ]
        ];
        $this->assertEquals($result, $data);
    }

    /**
     * Test model is automatically added to index on save.
     */
    public function testElasticSearchAddToIndex()
    {
        /** @var TestEntity $testEntity */
        $testEntity = factory(TestEntity::class)->create();

        sleep(1); //elastic search takes some time to index

        $search = $testEntity->searchByQuery([
            'match' => [
                'entity_id' => $testEntity->entity_id,
            ],
        ]);

        $this->assertEquals(1, $search->totalHits());

        $testEntity->delete(); //clean up so it doesn't remain in the index
    }

    /**
     * No abstract static methods
     * So we make small coverage hack here
     */
    public function testCoverageStatic()
    {
        TestEntity::createCustomIndexes();
        TestEntity::deleteCustomIndexes();
    }

    public function testElasticSearchRemoveFromIndex()
    {
        /** @var TestEntity $testEntity */
        $testEntity = factory(TestEntity::class)->create();

        $testEntity->delete();

        sleep(1); //elastic search takes some time to index

        $search = $testEntity->searchByQuery([
            'match' => [
                'entity_id' => $testEntity->entity_id,
            ],
        ]);

        $this->assertEquals(0, $search->totalHits());
    }

    public function testElasticSearchUpdateIndex()
    {
        /** @var TestEntity $testEntity */
        $testEntity = factory(TestEntity::class)->create();

        $testEntity->setAttribute('varchar', 'searchforthisvalue');
        $testEntity->save();

        sleep(1); //elastic search takes some time to index

        $search = $testEntity->searchByQuery([
            'match' => [
                'varchar' => 'searchforthisvalue',
            ],
        ]);

        $this->assertEquals(1, $search->totalHits());

        $testEntity->delete(); //clean up so it doesn't remain in the index
    }
}
