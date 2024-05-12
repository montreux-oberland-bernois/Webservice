<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Datasource\Query;

use Cake\Database\Expression\ComparisonExpression;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\Query\ReadQuery;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use TestApp\Webservice\StaticWebservice;

class ReadQueryTest extends TestCase
{
    protected ReadQuery $query;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->query = new ReadQuery(new StaticWebservice(), new Endpoint());
    }

    public function testAliasField()
    {
        $this->assertEquals(['field' => 'field'], $this->query->aliasField('field'));
    }

    public function testCount()
    {
        $this->assertEquals(3, $this->query->count());
    }

    public function testFirst()
    {
        $this->assertEquals(new Resource([
            'id' => 1,
            'title' => 'Hello World',
        ]), $this->query->first());
    }

    public function testApplyOptions()
    {
        $this->assertEquals($this->query, $this->query->applyOptions([
            'page' => 1,
            'limit' => 2,
            'order' => [
                'field' => 'ASC',
            ],
            'customOption' => 'value',
        ]));
        $this->assertEquals(1, $this->query->clause('page'));
        $this->assertEquals(2, $this->query->clause('limit'));
        $this->assertEquals([
            'field' => 'ASC',
        ], $this->query->clause('order'));
        $this->assertEquals([
            'customOption' => 'value',
        ], $this->query->getOptions());
    }

    public function testFind()
    {
        $this->query->getEndpoint()->setPrimaryKey('id');
        $this->query->getEndpoint()->setDisplayField('title');

        $this->assertEquals($this->query, $this->query->find('list'));

        $debugInfo = $this->query->__debugInfo();

        $this->assertIsCallable($debugInfo['formatters'][0]);
    }

    public function testPage()
    {
        $this->assertEquals($this->query, $this->query->page(10));

        $this->assertEquals(10, $this->query->clause('page'));
    }

    public function testPageWithLimit()
    {
        $this->assertEquals($this->query, $this->query->page(10, 20));

        $this->assertEquals(10, $this->query->clause('page'));
        $this->assertEquals(20, $this->query->clause('limit'));
    }

    public function testOffset()
    {
        $this->assertEquals($this->query, $this->query->offset(10));

        $this->assertEquals(10, $this->query->clause('offset'));
    }

    public function testOrderBy()
    {
        $this->assertEquals($this->query, $this->query->orderBy([
            'field' => 'ASC',
        ]));

        $this->assertEquals([
            'field' => 'ASC',
        ], $this->query->clause('order'));
    }

    public function testAllTwice()
    {
        $mockWebservice = $this
            ->getMockBuilder('\TestApp\Webservice\StaticWebservice')
            ->onlyMethods([
                'execute',
            ])
            ->getMock();

        $mockWebservice->expects($this->once())
            ->method('execute')
            ->willReturn(new ResultSet([
                new Resource([
                    'id' => 1,
                    'title' => 'Hello World',
                ]),
                new Resource([
                    'id' => 2,
                    'title' => 'New ORM',
                ]),
                new Resource([
                    'id' => 3,
                    'title' => 'Webservices',
                ]),
            ], 3));

        $this->query
            ->setWebservice($mockWebservice);

        $this->query->all();

        // This webservice shouldn't be called a second time
        $this->query->all();
    }

    public function testDebugInfo()
    {
        $this->assertEquals([
            '(help)' => 'This is a Query object, to get the results execute or iterate it.',
            'formatters' => [],
            'offset' => null,
            'page' => null,
            'limit' => null,
            'sort' => [],
            'extraOptions' => [],
            'conditions' => [],
            'repository' => new Endpoint(),
            'webservice' => new StaticWebservice(),
        ], $this->query->__debugInfo());
    }

    public function testJsonSerialize()
    {
        $expected = [
            ['id' => 1, 'title' => 'Hello World'],
            ['id' => 2, 'title' => 'New ORM'],
            ['id' => 3, 'title' => 'Webservices'],
        ];

        $this->assertEquals(json_encode($expected), json_encode($this->query));
    }

    public function testAndWhere()
    {
        $conditions = [
            'foo' => 'bar',
            'baz' => 2,
        ];
        $this->query->andWhere($conditions);

        $this->assertSame($conditions, $this->query->clause('where'));
    }

    public function testSelectWithArrayMerging()
    {
        $this->query->select(['id', 'name', 'title', 'description']);
        $this->assertSame(['id', 'name', 'title', 'description'], $this->query->clause('select'));

        $this->query->select(['published']);
        $this->assertSame(['id', 'name', 'title', 'description', 'published'], $this->query->clause('select'));
    }

    public function testSelectWithArrayOverwrite()
    {
        $firstFields = ['id', 'first_name', 'last_name', 'date_of_birth'];
        $this->query->select($firstFields);
        $this->assertSame($firstFields, $this->query->clause('select'));

        $secondFields = ['id', 'username', 'email'];
        $this->query->select($secondFields, true);
        $this->assertSame($secondFields, $this->query->clause('select'));
    }

    public function testSelectWithExpression()
    {
        $exp = new ComparisonExpression('upvotes', 50, 'integer', '>=');
        $this->query->select($exp);

        /** @var ComparisonExpression $comparisonClause */
        $comparisonClause = $this->query->clause('select')[0];

        $this->assertInstanceOf(ComparisonExpression::class, $comparisonClause);
        $this->assertEquals(50, $comparisonClause->getValue());
        $this->assertEquals('>=', $comparisonClause->getOperator());
    }

    public function testSelectWithCallable()
    {
        $fields = ['id', 'username', 'email', 'biography'];

        $callable = function () use ($fields) {
            return $fields;
        };
        $this->query->select($callable);

        $this->assertSame($fields, $this->query->clause('select'));
    }
}
