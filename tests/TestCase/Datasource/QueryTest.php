<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Datasource;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Model\Endpoint;
use TestApp\Webservice\StaticWebservice;

class QueryTest extends TestCase
{
    protected Query $query;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->query = new Query(new StaticWebservice(), new Endpoint());
    }

    public function testAction()
    {
        $this->assertNull($this->query->clause('action'));

        $this->assertEquals($this->query, $this->query->action(Query::ACTION_READ));
        $this->assertEquals(Query::ACTION_READ, $this->query->clause('action'));
    }

    public function testActionMethods()
    {
        $this->assertEquals($this->query, $this->query->create());
        $this->assertEquals(Query::ACTION_CREATE, $this->query->clause('action'));

        $this->assertEquals($this->query, $this->query->update());
        $this->assertEquals(Query::ACTION_UPDATE, $this->query->clause('action'));

        $this->assertEquals($this->query, $this->query->delete());
        $this->assertEquals(Query::ACTION_DELETE, $this->query->clause('action'));
    }

    public function testSet()
    {
        $this->query->update();

        $this->assertEquals($this->query, $this->query->set([
            'field' => 'value',
        ]));
        $this->assertEquals([
            'field' => 'value',
        ], $this->query->clause('set'));
    }
}
