<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

use Spira\Core\Model\Test\TestEntity;
use Illuminate\Support\Facades\Hash;
use Spira\Core\Model\Test\SecondTestEntity;

$factory->define(TestEntity::class, function (\Faker\Generator $faker) {

    return [
        'entity_id' => $faker->uuid,
        'varchar' => $faker->word,
        'hash' => Hash::make($faker->word),
        'integer' => $faker->numberBetween(0, 500),
        'decimal' => $faker->randomFloat(2, 0, 100),
        'boolean' => $faker->boolean(),
        'nullable' => null,
        'text' => $faker->paragraph(3),
        'date' => $faker->date(),
        'multi_word_column_title' => true,
        'hidden' => $faker->boolean(),
        'json' => [
            'varchar' => $faker->word,
            'integer' => $faker->numberBetween(0, 500),
        ],
    ];
});

$factory->define(SecondTestEntity::class, function ($faker) {
    return [
        'entity_id' => $faker->uuid,
        'check_entity_id' => $faker->uuid,
        'value' => $faker->word,
    ];
});

$factory->defineAs(TestEntity::class, 'custom', function ($faker) use ($factory) {
    $testEntity = $factory->raw(TestEntity::class);

    return array_merge($testEntity, ['varchar' => 'custom']);
});
