<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests\integration;

use Spira\Core\tests\TestCase;

/**
 * Class RestExceptionTest.
 * @group integration
 */
class CorsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->app->group([], function ($app) {
            require __DIR__.'/test_routes.php';
        });
    }

    public function testCorsHeadersAdded()
    {
        $this->makeRequest('OPTIONS', '/test/cors');
        $this->requestJson('OPTIONS', '/test/cors', [], [
            'Origin' => 'not.the.same.origin',
            'access-control-allow-headers' => 'ACCEPT, AUTHORIZATION, RANGE, WITH-NESTED',
            'access-control-allow-methods' => 'GET, POST, PUT, PATCH, DELETE',
            'Access-Control-Allow-Origin' => 'not.the.same.origin',
        ]);

        $this->assertResponseStatus(200);

        $this->assertTrue($this->response->headers->has('access-control-allow-origin'), 'Access-Control-Allow-Origin header is set');
    }

    /**
     * Internal exception test.
     */
    public function testInternalExceptionCorsHeadersAdded()
    {
        $this->getJson('/test/internal-exception');

        $this->assertResponseStatus(500);
        $this->shouldReturnJson();

        $object = json_decode($this->response->getContent());

        $this->assertTrue(is_object($object), 'Response is an object');

        $this->assertObjectHasAttribute('message', $object);
        $this->assertTrue(is_string($object->message), 'message attribute is text');
        $this->assertTrue($this->response->headers->has('access-control-allow-origin'), 'Access-Control-Allow-Origin header is set');
    }
}
