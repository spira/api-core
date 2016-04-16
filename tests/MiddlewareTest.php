<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\tests;

use Illuminate\Http\Request;
use Spira\Core\Middleware\TransformInputDataMiddleware;

class MiddlewareTest extends TestCase
{
    public function testBase64Decode()
    {
        $enc = base64_encode(json_encode(['will_not_be_decoded']));

        $request = new Request();
        $request->headers->add([
            'Base64-Encoded-Fields' => 'plainArray,some_string',
        ]);
        $request->offsetSet('plain_array', base64_encode(json_encode(['ololo' => 123])));
        $request->offsetSet('someString', base64_encode('abc'));
        $request->offsetSet('not_encoded', $enc);

        $result = $this->executeRequestWithMiddleware($request)->all();

        $this->assertEquals(123, array_get($result, 'plain_array.ololo'));
        $this->assertEquals('abc', array_get($result, 'some_string'));
        $this->assertEquals($enc, array_get($result, 'not_encoded'));
    }

    /**
     * Test TransformInputData middleware.
     *
     * @return void
     */
    public function testTransformInputData()
    {
        $request = new Request([
            'firstName' => 'foo',
            'lastname'  => 'bar',
        ]);

        $result = $this->executeRequestWithMiddleware($request)->all();

        $this->assertArrayEquals(['first_name', 'lastname'], array_keys($result));
    }

    public function testTransformInputDataNested()
    {
        // Create a request object to test
        $request = new Request();
        $request->offsetSet('firstName', 'foo');
        $request->offsetSet('lastname', 'bar');
        $request->offsetSet('nestedArray', ['fooBar' => 'bar', 'foo' => 'bar', 'oneMore' => ['andThis' => true]]);

        $result = $this->executeRequestWithMiddleware($request)->all();

        $this->assertArrayEquals(['first_name', 'lastname', 'nested_array'], array_keys($result));
        $this->assertEquals('bar', array_get($result, 'nested_array.foo_bar'));
        $this->assertTrue(array_get($result, 'nested_array.one_more.and_this'));
    }

    /** @return Request */
    protected function executeRequestWithMiddleware(Request $request)
    {
        $mw = new TransformInputDataMiddleware();

        return $mw->handle(
            $request,
            function ($request) {
                return $request;
            }
        );
    }
}
