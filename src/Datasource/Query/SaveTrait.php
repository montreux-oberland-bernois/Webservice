<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

trait SaveTrait
{
    /**
     * Set fields to save in resources
     *
     * @param array $fields The field to set
     * @return $this
     */
    public function set(array $fields)
    {
        $this->_parts['set'] = $fields;

        return $this;
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
