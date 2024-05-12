<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\QueryType;

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
}
