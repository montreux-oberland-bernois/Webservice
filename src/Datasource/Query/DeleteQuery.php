<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;

class DeleteQuery extends Query
{
    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'where' => [],
        'action' => self::ACTION_DELETE,
    ];
}
