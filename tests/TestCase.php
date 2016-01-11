<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests;

use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Testing\TestCase as LumenTestCase;
use Spira\Core\tests\Extensions\AssertionsTrait;
use Spira\Core\tests\Extensions\ModelFactoryTrait;
use Spira\Core\tests\Extensions\TestRequestsTrait;

class TestCase extends LumenTestCase
{
    use AssertionsTrait, ModelFactoryTrait, TestRequestsTrait;

    protected $authHeader;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->bootTraits();

        DB::connection()->beginTransaction(); //start a new transaction
    }

    public function tearDown()
    {
        DB::connection()->rollBack(); //rollback the transaction so the test case can be rerun without duplicate key exceptions
        DB::connection()->setPdo(null); //close the pdo connection to `avoid too many connections` errors
        parent::tearDown();
    }

    /**
     * Allow traits to have custom initialization built in.
     *
     * @return void
     */
    protected function bootTraits()
    {
        foreach (get_declared_traits() as $trait) {
            if (method_exists($this, 'boot'.class_basename($trait))) {
                $this->{'boot'.class_basename($trait)}();
            }
        }
    }

    /**
     * Creates the application.
     *
     * @return \Laravel\Lumen\Application
     */
    public function createApplication()
    {
        return require __DIR__.'/../bootstrap/app.php';
    }

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @param  array $headers
     *
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers)
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $name = strtr(strtoupper($name), '-', '_');
            $server[$name] = $value; //set the server header to SNAKE_CASE

            if (! starts_with($name, 'HTTP_')) {
                $name = 'HTTP_'.$name;
                $server[$name] = $value; //add the HTTP_* key
            }
        }

        return $server;
    }

    /**
     * Visit the given URI with a [$method] request with content type of application/json.
     *
     * @param $method
     * @param  string $uri
     * @param  array $data
     * @param  array $headers
     * @return $this
     */
    public function requestJson($method, $uri, array $data = [], array $headers = [])
    {
        $content = json_encode($data);

        $headers = $this->addJsonHeaders($headers, $content);
        $headers = $this->addTokenHeaders($headers);
        $server = $this->transformHeadersToServerVars($headers);

        $this->call($method, $uri, [], [], [], $server, $content);

        return $this;
    }

    /**
     * Make a request of any type.
     * @param $method
     * @param $uri
     * @param $content
     * @param array $headers
     * @return $this
     */
    public function makeRequest($method, $uri, $content = null, array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call($method, $uri, [], [], [], $server, $content);

        return $this;
    }

    /**
     * @param array $headers
     * @param $content
     * @return array
     */
    protected function addJsonHeaders(array $headers, $content)
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Content-Length'] = mb_strlen($content, '8bit');
        if (! isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json';

            return $headers;
        }

        return $headers;
    }

    /**
     * @param array $headers
     * @return array
     */
    protected function addTokenHeaders(array $headers)
    {
        if ($this->authHeader && ! isset($headers['HTTP_AUTHORIZATION'])) {
            $headers['HTTP_AUTHORIZATION'] = $this->authHeader;
        }

        return $headers;
    }

    /**
     * Visit the given URI with a GET request with content type of application/json.
     *
     * @param  string $uri
     * @param  array $headers
     * @return $this
     */
    public function getJson($uri, array $headers = [])
    {
        return $this->requestJson('GET', $uri, [], $headers);
    }

    /**
     * Visit the given URI with a POST request with content type of application/json.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function postJson($uri, array $data = [], array $headers = [])
    {
        return $this->requestJson('POST', $uri, $data, $headers);
    }

    /**
     * Visit the given URI with a PUT request with content type of application/json.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function putJson($uri, array $data = [], array $headers = [])
    {
        return $this->requestJson('PUT', $uri, $data, $headers);
    }

    /**
     * Visit the given URI with a PATCH request with content type of application/json.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function patchJson($uri, array $data = [], array $headers = [])
    {
        return $this->requestJson('PATCH', $uri, $data, $headers);
    }

    /**
     * Visit the given URI with a DELETE request with content type of application/json.
     *
     * @param  string  $uri
     * @param  array  $data
     * @param  array  $headers
     * @return $this
     */
    public function deleteJson($uri, array $data = [], array $headers = [])
    {
        return $this->requestJson('DELETE', $uri, $data, $headers);
    }
}
