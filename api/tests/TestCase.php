<?php

use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Debug\Dumper;

class TestCase extends Laravel\Lumen\Testing\TestCase
{
    use AssertionsTrait, HelpersTrait;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        $this->bootTraits();

        parent::setUp();

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
        foreach (class_uses($this) as $trait) {
            if (method_exists($this, 'boot'.$trait)) {
                $this->{'boot'.$trait}();
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


    public function assertResponseStatus($code)
    {
        try {
            parent::assertResponseStatus($code);
        }catch(\PHPUnit_Framework_ExpectationFailedException $e){
            $content = $this->response->getContent();

            $json = json_decode($content);

            if (json_last_error() === JSON_ERROR_NONE) {
                $content = $json;
            }

            (new Dumper)->dump($content);
            throw $e;
        }
    }

}
