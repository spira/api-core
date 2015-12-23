<?php


namespace Spira\Core\tests\integration;


use Spira\Core\Controllers\ApiController;

class ControllerWithDefaultAuth extends ApiController
{
    protected $permissionsEnabled = true;
    protected $defaultRole = 'some_default';


    public function getOne()
    {
        $this->checkPermission('getOne');
        return $this->getResponse()->noContent();
    }
}