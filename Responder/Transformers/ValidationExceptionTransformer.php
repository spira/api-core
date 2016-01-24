<?php


namespace Spira\Core\Responder\Transformers;


use Illuminate\Support\Arr;

class ValidationExceptionTransformer extends EloquentModelTransformer
{
    /**
     * @param $array
     * @return mixed
     */
    protected function handleCase($array)
    {
        foreach ($array as $key => $value) {
            $this->handleSnakeCase($array, $key, $value);
            $this->handleDotCase($array, $key, $value);
        }

        return $array;
    }

    protected function handleDotCase(&$array, $key, $value)
    {
        if (strpos($key, '.') !== false){
            unset($array[$key]);
            Arr::set($array, $key, $value);
        }
    }
}