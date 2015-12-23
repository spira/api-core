<?php


namespace Spira\Core\tests\integration;


use Spira\Core\Controllers\ApiController;

class ControllerWithAuth extends ApiController
{

    protected $permissionsEnabled = true;
    protected $defaultRole = false;

    public function getOne()
    {
        $this->checkPermission('getOne');
        return $this->getResponse()->setContent(json_encode('1'));
    }

}