<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PagesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testSkeletonDiagnosticsAreNotRouted(): void
    {
        $this->get('/pages/home');
        $this->assertResponseCode(404);
        $this->assertHeader('Cache-Control', 'private, no-store');
    }
}
