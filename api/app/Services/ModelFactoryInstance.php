<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Http\Transformers\BaseTransformer;
use App\Http\Transformers\IlluminateModelTransformer;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

class ModelFactoryInstance implements Arrayable, Jsonable
{
    private $transformerService;
    private $factoryInstance;
    private $customizations = [];
    private $entityCount = 1;
    private $transformer;
    private $makeVisible;
    private $showOnly;
    private $hide;
    private $entityType;
    private $appends = [];

    /**
     * New model instance.
     *
     * @param $factoryInstance
     * @param $transformerService
     */
    public function __construct($factoryInstance, $transformerService)
    {
        $this->factoryInstance = $factoryInstance;
        $this->transformerService = $transformerService;
    }

    /**
     * Set number of entities to create (not required, default is 1).
     *
     * @param $number
     *
     * @return $this
     */
    public function count($number)
    {
        $this->entityCount = $number;

        return $this;
    }

    /**
     * Set custom value overrides for the entity.
     *
     * @param $customizations
     *
     * @return $this
     */
    public function customize(array $customizations)
    {
        $this->customizations = $customizations;

        return $this;
    }

    /**
     * Make otherwise hidden parameters visible.
     *
     * @param $makeVisible
     *
     * @return $this
     */
    public function makeVisible($makeVisible)
    {
        $this->makeVisible = $makeVisible;

        return $this;
    }

    /**
     * Limit what properties the factory instance returns.
     *
     * @param $showOnly
     *
     * @return $this
     */
    public function showOnly($showOnly)
    {
        $this->showOnly = $showOnly;

        return $this;
    }

    /**
     * Hide attributes the factory instance returns.
     *
     * @param  array  $hide
     * @return $this
     */
    public function hide(array $hide)
    {
        $this->hide = $hide;

        return $this;
    }

    /**
     * Add properties to the returned entity
     *
     * @param $key
     * @param $value
     *
     * @return $this
     */
    public function append($key, $value)
    {
        $this->appends[$key] = $value;

        return $this;
    }

    /**
     * Make the model factory use a custom transformer
     * eg
     * $factory->get(\App\Models\UserCredential::class)
     *      ->setTransformer(\App\Http\Transformers\UserTransformer::class)
     *      ->toArray();.
     *
     * @param $transformerName
     *
     * @return $this
     */
    public function setTransformer($transformerName)
    {
        $this->transformer = new $transformerName($this->transformerService);
        return $this;
    }

    /**
     * Get the built entities.
     *
     * @return mixed
     */
    private function built()
    {
        $entity = $this->factoryInstance
            ->times($this->entityCount)
            ->make($this->customizations);

        $this->setEntityType();

        return $entity;
    }

    /**
     * Set if the entity is a single item or a collection.
     *
     * @return void
     */
    protected function setEntityType()
    {
        $this->entityType = ($this->entityCount > 1) ? 'collection' : 'item';
    }

    /**
     * Modify an entity.
     *
     * @param $entity
     */
    private function modifyEntity($entity)
    {
        if ($this->showOnly) {
            $attributes = $entity->getAttributes();
            $appends = $entity->appends;
            $modifiedArray = array_keys($attributes);
            if (!empty($appends)) {
                $modifiedArray = array_merge($modifiedArray, $appends);
            }
            $newHidden = array_diff($modifiedArray, $this->showOnly);
            $entity->setHidden($newHidden);
        }

        if ($this->makeVisible) {
            $hidden = $entity->getHidden();

            $newHidden = array_diff($hidden, $this->makeVisible);

            $entity->setHidden($newHidden);
        }

        if ($this->hide) {
            $hidden = $entity->getHidden();

            $newHidden = array_merge($hidden, $this->hide);

            $entity->setHidden($newHidden);
        }

        if (!empty($this->appends)) {
            foreach ($this->appends as $appendKey => $appendValue) {
                $entity->{$appendKey} = $appendValue;
            }
        }

        return $entity;
    }

    /**
     * Get the modified entity[ies].
     *
     * @return mixed
     */
    public function modified()
    {
        $entity = $this->built();
        switch ($this->entityType) {
            case 'item':
                $entity = $this->modifyEntity($entity);
                break;
            case 'collection':
                $entity = $entity->each(
                    function ($singleEntity) {
                        return $this->modifyEntity($singleEntity);
                    }
                );
                break;
        }

        return $entity;
    }

    /**
     * Get the transformed entity[ies].
     *
     * @return mixed
     */
    public function transformed()
    {
        if ($this->factoryInstance instanceof Collection) {
            $entity = $this->factoryInstance->slice(0, $this->entityCount);
            $this->setEntityType();
        } else {
            $entity = $this->modified();
        }

        if (!$this->transformer) {
            $this->transformer = new IlluminateModelTransformer($this->transformerService);
        }

        $transformedEntity = $this->transformerService->{$this->entityType}($entity, $this->transformer);

        return $transformedEntity;
    }

    /**
     * Get the built & modified entity[ies]
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->modified()->toArray();
    }

    /**
     * Get the JSON encoded string of the (built, modified, transformed) entity[ies].
     *
     * @param int $options
     *
     * @return string
     */
    public function json($options = JSON_PRETTY_PRINT)
    {
        $transformed = $this->transformed();

        $jsonEncoded = json_encode($transformed, $options);

        return str_replace("\n", "\n            ", $jsonEncoded); //cheap trick to make sure the 12 deep indentation requirement of apiary is preserved
    }

    /**
     * {@inheritdoc}
     */
    public function toJson($options = 0)
    {
        return $this->json($options);
    }
}