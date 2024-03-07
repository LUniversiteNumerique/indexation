<?php

namespace App\Tests\Controller;

use App\Controller\Admin\CategoryCrudController;
use App\Controller\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

final class AuteurCrudControllerTest extends AbstractCrudTestCase
{
    protected function getControllerFqcn(): string
    {
        return CategoryCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testIndexPage(): void
    {
        // this examples doesn't use security; in your application you may need to ensure that the user is logged before the test
        $this->client->request("GET", $this->generateIndexUrl());
        AuteurCrudControllerTest::assertResponseIsSuccessful();
    }
}