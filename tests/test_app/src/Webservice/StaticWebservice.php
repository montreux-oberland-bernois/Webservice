<?php
declare(strict_types=1);

namespace TestApp\Webservice;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Datasource\Schema;
use Muffin\Webservice\Test\TestCase\Fixture\ResourceFixture;
use Muffin\Webservice\Webservice\WebserviceInterface;

class StaticWebservice implements WebserviceInterface
{
    public function execute(Query $query, array $options = []): ResultSet
    {
        return new ResultSet(ResourceFixture::getFixtures(), 3);
    }

    public function describe(string $endpoint): Schema
    {
        return new Schema($endpoint, [
           'id' => [
               'type' => 'integer',
           ],
            'title' => [
                'type' => 'string',
            ],
        ]);
    }
}
