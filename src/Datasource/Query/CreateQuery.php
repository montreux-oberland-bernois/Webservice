<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\QueryType;

class CreateQuery extends Query
{
    use SaveTrait;

    protected QueryType $_type = QueryType::CREATE;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'set' => [],
    ];

    /**
     * Return a handy representation of the query
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        $data = parent::__debugInfo();
        $data['set'] = $this->_parts['set'];

        return $data;
    }
}
