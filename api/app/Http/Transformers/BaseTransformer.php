<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace App\Http\Transformers;

use App\Services\TransformerService;
use League\Fractal\TransformerAbstract;
use Spira\Model\Collection\Collection;
use Spira\Responder\Contract\TransformerInterface;

abstract class BaseTransformer extends TransformerAbstract  implements TransformerInterface
{
    /**
     * @var TransformerService
     */
    private $service;
    protected $options = [];

    public function __construct(TransformerService $service)
    {
        $this->service = $service;
    }

    /**
     * @param $object
     * @return mixed
     */
    abstract public function transform($object);

    /**
     * @return TransformerService
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param $collection
     * @param array $options
     * @return mixed
     */
    public function transformCollection($collection, array $options = [])
    {
        $this->options = $options;

        if ($collection instanceof Collection) {
            $collection = $collection->all(); //remove the items marked as deleted
        }

        return $this->getService()->collection($collection, $this);
    }

    /**
     * @param $item
     * @param array $options
     * @return mixed
     */
    public function transformItem($item, array $options = [])
    {
        if (is_null($item)) {
            return $item;
        }

        $this->options = $options;

        return $this->getService()->item($item, $this);
    }
}
