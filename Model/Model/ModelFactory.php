<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Model\Model;

use Illuminate\Support\Facades\App;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Factory;
use Spira\Core\Responder\TransformerService;

class ModelFactory
{
    protected $transformerService;
    /**
     * @var Factory
     */
    protected $factory;

    /**
     * Initialise the factory.
     */
    public function __construct()
    {
        $this->factory = Container::getInstance()->make('Illuminate\Database\Eloquent\Factory');
        $this->transformerService = app(TransformerService::class);
    }

    /**
     * Get a factory instance.
     *
     * @param $factoryClass
     * @param $definedName
     *
     * @return ModelFactoryInstance
     */
    public function get($factoryClass = null, $definedName = 'default')
    {
        if (is_string($factoryClass)) {
            $this->factory = Container::getInstance()->make('Illuminate\Database\Eloquent\Factory');
            $instance = $this->factory->of($factoryClass, $definedName);
        } else {
            $instance = null;
        }

        return new ModelFactoryInstance($instance, $this->transformerService);
    }

    /**
     * Shorthand get a json string of the entity.
     *
     * @param $factoryClass
     * @param string $definedName
     *
     * @return ModelFactoryInstance
     */
    public function json($factoryClass, $definedName = 'default')
    {
        return $this->get($factoryClass, $definedName)->json();
    }

    /**
     * Shorthand get the eloquent entity.
     *
     * @param $factoryClass
     * @param string $definedName
     *
     * @return mixed
     */
    public function make($factoryClass, $definedName = 'default')
    {
        return $this->get($factoryClass, $definedName)->modified();
    }
}
