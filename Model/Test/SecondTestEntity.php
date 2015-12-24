<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Model\Test;

use Spira\Core\Model\Model\BaseModel;
use Spira\Core\Model\Model\IndexedModel;
use Spira\Core\Model\Model\LocalizableModelInterface;
use Spira\Core\Model\Model\LocalizableModelTrait;

class SecondTestEntity extends BaseModel implements LocalizableModelInterface
{
    use LocalizableModelTrait;

    public $table = 'second_test_entities';

    protected $primaryKey = 'entity_id';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['entity_id', 'check_entity_id', 'value'];

    protected static $validationRules = [
        'entity_id' => 'required|uuid',
        'check_entity_id' => 'uuid',
        'value' => 'required|string',
    ];

    public function testEntities()
    {
        return $this->belongsToMany(TestEntity::class, 'test_many_many', 'test_second_id', 'test_id');
    }
}
