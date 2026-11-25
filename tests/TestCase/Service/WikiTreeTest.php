<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\WikiTree;
use Cake\TestSuite\TestCase;

class WikiTreeTest extends TestCase
{
    public function testPageCanAlsoBeParentOfNestedPages(): void
    {
        $tree = WikiTree::build([
            ['path' => 'guides', 'title' => 'Guides'],
            ['path' => 'guides/start', 'title' => 'Start'],
            ['path' => 'reference/api', 'title' => 'API'],
        ]);
        $this->assertSame('Guides', $tree[0]['page']['title']);
        $this->assertSame('Start', $tree[0]['children']['start']['page']['title']);
        $this->assertNull($tree[1]['page']);
        $this->assertSame('API', $tree[1]['children']['api']['page']['title']);
    }
}
