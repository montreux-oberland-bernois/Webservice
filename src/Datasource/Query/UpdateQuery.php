<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\QueryType;
use Muffin\Webservice\Model\Resource;

class UpdateQuery extends Query
{
    use SaveTrait;

    protected QueryType $_type = QueryType::UPDATE;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'where' => [],
        'set' => [],
    ];

    /**
     * Execute the query
     *
     * @return \Muffin\Webservice\Model\Resource|int|bool
     */
    public function execute(): Resource|bool|int
    {
        $return = $this->_webservice->execute($this);

        assert(
            is_int($return) || is_bool($return) || $return instanceof Resource,
            sprintf(
                'UpdateQuery execution must return a resource, or an integer or a boolean, got `%s`',
                get_debug_type($return),
            ),
        );

        return $return;
    }
}
