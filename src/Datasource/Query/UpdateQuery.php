<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;

class UpdateQuery extends Query
{
    use SaveTrait;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'action' => self::ACTION_UPDATE,
        'where' => [],
        'set' => [],
    ];
}
