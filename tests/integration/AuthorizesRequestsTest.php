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

    protected $gate;

    public function setUp()
    {
        parent::setUp();
        $this->app->group([], function ($app) {
            require __DIR__.'/test_routes.php';
        });
        $this->gate = Mockery::mock(Gate::class);
        $this->app->singleton(Gate::class, function(){return $this->gate;});
    }

    public function testSimpleSuccess()
    {
        $this->app[Gate::class]->shouldReceive('check')->once()->with('getOne',[])->andReturn(true);

        $this->withAuthorization()->getJson('/test/simple-auth');
        $this->assertResponseOk();
        $object = $this->getJsonResponseAsObject();
        $this->assertEquals('1', $object);
    }

    public function testSimpleFail()
    {
        $this->app[Gate::class]->shouldReceive('check')->once()->with('getOne',[])->andReturn(false);
        $this->withAuthorization()->getJson('/test/simple-auth');
        $this->assertResponseStatus(Response::HTTP_FORBIDDEN);
    }

    public function testDefaultSuccess()
    {
        $this->app[Gate::class]->shouldReceive('check')->once()->with('some_default',[])->andReturn(true);
        $this->withAuthorization()->getJson('/test/default-auth');
        $this->assertResponseStatus(Response::HTTP_NO_CONTENT);
    }

    public function testDefaultFail()
    {
        $this->app[Gate::class]->shouldReceive('check')->once()->with('some_default',[])->andReturn(false);
        $this->withAuthorization()->getJson('/test/default-auth');
        $this->assertResponseStatus(Response::HTTP_FORBIDDEN);
    }
}