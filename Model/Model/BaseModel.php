<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Model\Model;

use Bosnadev\Database\Traits\UuidTrait;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Spira\Core\Model\Collection\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Class BaseModel.
 *
 * @method static int count
 * @method static BaseModel find($id)
 * @method static BaseModel first()
 * @method static BaseModel findOrFail($id)
 * @method static Collection get
 * @method static Collection findMany($ids)
 * @method static Builder where($value,$operator,$operand)
 * @method static Builder whereIn($column,$ids)
 * @method static Builder whereNotNull($column)
 * @method static Builder whereNull($column)
 * @method static BaseModel skip($offset)
 * @method static BaseModel take($limit)
 */
abstract class BaseModel extends Model
{
    use UuidTrait;

    public $incrementing = false;

    protected $casts = [
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    protected static $validationRules = [];

    /**
     * Temporary fix of polymorphic relation naming.
     *
     * @see https://github.com/laravel/framework/issues/10501#issuecomment-162705813
     *
     * {@inheritdoc}
     */
    public function morphTo($name = null, $type = null, $id = null)
    {
        // Get the name of the relation from the function name.
        list($current, $caller) = debug_backtrace(false, 2);
        $relation = Str::camel($caller['function']);

        // If no name is provided, we will use the name of the relation
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        if (is_null($name)) {
            $name = Str::snake($relation);
        }

        list($type, $id) = $this->getMorphs($name, $type, $id);

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. When that is the case we will pass in a dummy query as
        // there are multiple types in the morph and we can't use single queries.
        if (is_null($class = $this->$type)) {
            return new MorphTo(
                $this->newQuery(), $this, $id, null, $type, $relation
            );
        }

        // If we are not eager loading the relationship we will essentially treat this
        // as a belongs-to style relationship since morph-to extends that class and
        // we will pass in the appropriate values so that it behaves as expected.
        else {
            $class = $this->getActualClassNameForMorph($class);

            $instance = new $class;

            return new MorphTo(
                $instance->newQuery(), $this, $id, $instance->getKeyName(), $type, $relation
            );
        }
    }

    /**
     * @param null $entityId
     * @param array $requestEntity
     * @return array
     */
    public static function getValidationRules($entityId = null, array $requestEntity = [])
    {
        return static::$validationRules;
    }

    /**
     * Get the table name for the instance.
     * @return string
     */
    public static function getTableName()
    {
        return with(new static())->getTable();
    }

    /**
     * Get the primary key name for the instance.
     * @return string
     */
    public static function getPrimaryKey()
    {
        return with(new static())->getKeyName();
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     * @throws SetRelationException
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->getDates()) && $value) {
            if (! $value instanceof Carbon && ! $value instanceof \DateTime) {
                $value = new Carbon($value);
                $this->attributes[$key] = $value;

                return;
            }
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Fires an event for RevisionableTrait.
     *
     * @param  string $event
     * @param  array  $payload
     * @param  bool   $halt
     *
     * @return mixed
     */
    public function fireRevisionableEvent($event, array $payload, $halt = true)
    {
        $event = "eloquent.{$event}: ".get_class($this);
        $method = $halt ? 'until' : 'fire';

        return static::$dispatcher->$method($event, array_merge([$this], $payload));
    }

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param  array  $models
     * @return Collection
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models, static::class);
    }

    /**
     * @param array $routeParams
     * @return BaseModel
     */
    public function findByRouteParams(array $routeParams)
    {
        return $this->whereAll($routeParams)->firstOrFail();
    }

    /**
     * @param Builder $query
     * @param array $params
     * @return Builder
     */
    public function scopeWhereAll(Builder $query, array $params)
    {
        foreach ($params as $attribute => $value) {
            $query->where($attribute, $value);
        }

        return $query;
    }

    /**
     * @param mixed $id
     * @return BaseModel
     * @throws ModelNotFoundException
     */
    public function findByIdentifier($id)
    {
        return $this->findOrFail($id);
    }

    /**
     * Create a collection of models from a request collection
     * The method is more efficient if is passed a Collection of existing entries otherwise it will do a query for every entity.
     * @param array $requestCollection
     * @param EloquentCollection|null $existingModels
     * @return Collection
     */
    public function hydrateRequestCollection(array $requestCollection, EloquentCollection $existingModels = null)
    {
        $keyName = $this->getKeyName();

        $models = array_map(function ($item) use ($keyName, $existingModels) {

            /** @var Model $model */
            $model = null;
            $entityId = isset($item[$keyName]) ? $item[$keyName] : null;

            //if we have known models, get the model from the collection
            if ($existingModels) {
                $model = $existingModels->get($entityId);
            }

            //if the model couldn't be found, find it in the database directly, or create a new one
            if (! $model) {
                $model = $this->findOrNew($entityId);
            }

            $model->fill($item);

            return $model;
        }, $requestCollection);

        return $this->newCollection($models);
    }

    /**
     * Handle case where the value might be from Carbon::toArray.
     * @param mixed $value
     * @return Carbon|static
     */
    protected function asDateTime($value)
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return Carbon::instance($value);
        }

        if (is_array($value) && isset($value['date'])) {
            return Carbon::parse($value['date'], $value['timezone']);
        }

        try {
            return Carbon::createFromFormat(Carbon::ISO8601, $value); //try decode ISO8601 date
        } catch (\InvalidArgumentException $e) {
            return parent::asDateTime($value);
        }
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        //if cast type is json and the object already is decoded, don't try to re-decode
        if (in_array($this->getCastType($key), ['array', 'json', 'object']) && (is_array($value) || is_object($value))) {
            return $value;
        }

        // Run the parent cast rules in the parent method
        $value = parent::castAttribute($key, $value);

        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'date':

                try {
                    return $this->asDateTime($value); //otherwise try the alternatives
                } catch (\InvalidArgumentException $e) {
                    return Carbon::createFromFormat('Y-m-d', $value); //if it is the true base ISO8601 date format, parse it
                }

            case 'datetime':
                return $this->asDateTime($value); //try the catchall method for date translation
            default:
                return $value;
        }
    }

    /**
     * Register a an after boot model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function booted($callback, $priority = 0)
    {
        static::registerModelEvent('booted', $callback, $priority);
    }
}
