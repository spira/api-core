<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Model\Test;

use Illuminate\Database\Eloquent\Collection;
use Spira\Core\Model\Model\BaseModel;
use Spira\Core\Model\Model\LocalizableModelInterface;
use Spira\Core\Model\Model\LocalizableModelTrait;

/**
 * Class OrderedTestEntity.
 *
 * @property Collection $testMany
 */
class OrderedTestEntity extends BaseModel implements LocalizableModelInterface
{
    use LocalizableModelTrait;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    public $table = 'ordered_test_entities';
    protected $primaryKey = 'entity_id';
    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['entity_id', 'varchar', 'integer'];
    /**
     * Override default order by value.
     *
     * @var string
     */
    protected $defaultOrderBy = 'varchar';
}
