<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

class TransformInputDataMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->transformRequestInput($request, $request->query);
        $this->transformRequestInput($request, $request->json());

        return $next($request);
    }

    /**
     * Recursively rename keys in nested arrays.
     *
     * @param array $array
     *
     * @return array
     */
    protected function renameKeys(array $array)
    {
        $newArray = [];
        foreach ($array as $key => $value) {

            // Recursively check if the value is an array that needs parsing too
            $value = (is_array($value)) ? $this->renameKeys($value) : $value;

            // Convert camelCase to snake_case
            if (is_string($key) && ! ctype_lower($key)) {
                $newArray[snake_case($key)] = $value;
            } else {
                $newArray[$key] = $value;
            }
        }

        return $newArray;
    }

    /**
     * Transform the input to snake_case keys and process encoded input values
     * @param Request $request
     * @param ParameterBag $input
     */
    protected function transformRequestInput(Request $request, ParameterBag $input)
    {
        foreach ($input as $key => $value) {

            $value = $this->extractEncodedJson($input, $key, $value);

            // Handle snakecase conversion in sub arrays
            if (is_array($value)) {
                $value = $this->renameKeys($value);
                $input->set($key, $value);
            }

            // Find any potential camelCase keys in the 'root' array, and convert
            // them to snake_case
            if (! ctype_lower($key)) {
                // Only convert if the key will change
                if ($key != snake_case($key)) {
                    $input->set(snake_case($key), $value);
                    $input->remove($key);
                }
            }
        }
    }


    /**
     * If the input is both base64 encoded and json encoded extract it to array
     * @param ParameterBag $input
     * @param $key
     * @param $value
     * @return mixed
     */
    private function extractEncodedJson(ParameterBag $input, $key, $value)
    {

        //if it's not a string it can't be base64 encoded, exit early
        if (!is_string($value)){
            return $value;
        }

        $decoded = base64_decode($value, true);

        //strict mode above allows for a quick check to see if the value is not encoded
        if (!$decoded){
            return $value;
        }

        $jsonParsed = json_decode($decoded, true);

        //if value couldn't be json decoded, it wasn't valid json, return the original value
        if (!$jsonParsed){
            return $value;
        }

        $input->set($key, $jsonParsed);
        return $jsonParsed;
    }

}
