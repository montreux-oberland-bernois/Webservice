<?php

namespace Muffin\Webservice\Test\TestCase\Fixture;

use Muffin\Webservice\Model\Resource;

class ResourceFixture {
    public static function getFixtures(): array {
        $resourceOptions = [
            'markClean' => true,
            'useSetters' => false
        ];

        return [
            new Resource([
                'id' => 1,
                'title' => 'Hello World',
            ],$resourceOptions),
            new Resource([
                'id' => 2,
                'title' => 'New ORM',
            ],$resourceOptions),
            new Resource([
                'id' => 3,
                'title' => 'Webservices',
            ],$resourceOptions)
        ];
    }
}
