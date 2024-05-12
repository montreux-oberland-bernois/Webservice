<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\QueryType;

class DeleteQuery extends Query
{
    protected QueryType $_type = QueryType::DELETE;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'where' => [],
    ];
}
