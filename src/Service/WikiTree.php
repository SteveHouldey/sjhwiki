<?php
declare(strict_types=1);

namespace App\Service;

class WikiTree
{
    /**
     * Build a navigable tree while allowing a page to also contain child pages.
     *
     * @param array $pages Pages ordered by path.
     * @return array
     */
    public static function build(array $pages): array
    {
        $tree = [];
        foreach ($pages as $page) {
            $segments = explode('/', $page['path']);
            $cursor = &$tree;
            foreach ($segments as $index => $segment) {
                if (!isset($cursor[$segment])) {
                    $cursor[$segment] = ['name' => $segment, 'page' => null, 'children' => []];
                }
                if ($index === count($segments) - 1) {
                    $cursor[$segment]['page'] = $page;
                }
                $cursor = &$cursor[$segment]['children'];
            }
            unset($cursor);
        }

        return array_values($tree);
    }
}
