<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\QueryType;
use Muffin\Webservice\Model\Resource;

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
     * Execute the query
     *
     * @return \Muffin\Webservice\Model\Resource|bool
     */
    public function execute(): Resource|bool
    {
        $return = $this->_webservice->execute($this);

        assert(
            $return instanceof Resource || is_bool($return),
            sprintf('CreateQuery execution must return a resource or a boolean, got `%s`', get_debug_type($return))
        );

        return $return;
    }

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
