<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\QueryType;

class DeleteQuery extends Query
{
    protected QueryType $_type = QueryType::DELETE;

    /**
     * Execute the query
     *
     * @return int|bool
     */
    public function execute(): int|bool
    {
        $return = $this->_webservice->execute($this);

        assert(
            is_int($return) || is_bool($return),
            sprintf('DeleteQuery execution must return an integer or a boolean, got `%s`', get_debug_type($return))
        );

        return $return;
    }
}
