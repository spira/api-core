<?php

namespace Spira\Core\tests\integration;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Response;
use Spira\Core\tests\Extensions\WithAuthorizationMockTrait;
use Spira\Core\tests\TestCase;
use Mockery;

class AuthorizesRequestsTest extends TestCase
{
    use WithAuthorizationMockTrait;

    public function setUp()
    {
        parent::setUp();

        app()->group([], function ($app) {
            require __DIR__.'/test_routes.php'; // $app is used in required file!
        });

        app()->extend(Gate::class, function(){
            return Mockery::mock(Gate::class);
        });
    }

    public function testSimpleSuccess()
    {
        app(Gate::class)->shouldReceive('check')->once()->with('getOne', [])->andReturn(true);
        $this->withAuthorization()->getJson('/test/simple-auth');
        $this->assertResponseOk();
        $object = $this->getJsonResponseAsObject();
        $this->assertEquals('1', $object);
    }

    public function testSimpleFail()
    {
        app(Gate::class)->shouldReceive('check')->once()->with('getOne', [])->andReturn(false);
        $this->withAuthorization()->getJson('/test/simple-auth');
        $this->assertResponseStatus(Response::HTTP_FORBIDDEN);
    }

    public function testDefaultSuccess()
    {
        app(Gate::class)->shouldReceive('check')->once()->with('some_default', [])->andReturn(true);
        $this->withAuthorization()->getJson('/test/default-auth');
        $this->assertResponseStatus(Response::HTTP_NO_CONTENT);
    }

    public function testDefaultFail()
    {
        app(Gate::class)->shouldReceive('check')->once()->with('some_default', [])->andReturn(false);
        $this->withAuthorization()->getJson('/test/default-auth');
        $this->assertResponseStatus(Response::HTTP_FORBIDDEN);
    }
}
