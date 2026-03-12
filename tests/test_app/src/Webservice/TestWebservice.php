<?php
declare(strict_types=1);

namespace TestApp\Webservice;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Webservice\Driver\AbstractDriver;
use Muffin\Webservice\Webservice\Webservice;

class TestWebservice extends Webservice
{
    protected function _executeReadQuery(Query $query, array $options = []): ResultSet
    {
        return new ResultSet([], 0);
    }

    public function createResource($resourceClass, array $properties = []): Resource
    {
        return $this->_createResource($resourceClass, $properties);
    }

    /**
     * @return Resource[]
     */
    public function transformResults(Endpoint $endpoint, array $results): array
    {
        return $this->_transformResults($endpoint, $results);
    }

    public function setDriver(AbstractDriver $driver): self
    {
        $this->_driver = $driver;

        return $this;
    }
}
