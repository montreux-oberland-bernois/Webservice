<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Datasource;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\Query\CreateQuery;
use Muffin\Webservice\Model\Endpoint;
use TestApp\Webservice\StaticWebservice;

class CreateQueryTest extends TestCase
{
    protected CreateQuery $query;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->query = new CreateQuery(new StaticWebservice(), new Endpoint());
    }

    public function testSet()
    {
        $this->assertEquals($this->query, $this->query->set([
            'field' => 'value',
        ]));
        $this->assertEquals([
            'field' => 'value',
        ], $this->query->clause('set'));
    }
}
