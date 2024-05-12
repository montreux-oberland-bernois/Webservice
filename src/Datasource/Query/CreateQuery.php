<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;

class CreateQuery extends Query
{
    use SaveTrait;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'action' => self::ACTION_CREATE,
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
