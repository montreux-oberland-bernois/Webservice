<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\Utility\Hash;
use Closure;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Webservice\WebserviceInterface;

class Query
{
    public const ACTION_CREATE = 1;
    public const ACTION_READ = 2;
    public const ACTION_UPDATE = 3;
    public const ACTION_DELETE = 4;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'where' => [],
    ];

    /**
     * Instance of the webservice to use
     *
     * @var \Muffin\Webservice\Webservice\WebserviceInterface
     */
    protected WebserviceInterface $_webservice;

    /**
     * Instance of a endpoint object this query is bound to
     *
     * @var \Muffin\Webservice\Model\Endpoint
     */
    protected Endpoint $_endpoint;

    /**
     * Construct the query
     *
     * @param \Muffin\Webservice\Webservice\WebserviceInterface $webservice The webservice to use
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint this is executed from
     */
    public function __construct(WebserviceInterface $webservice, Endpoint $endpoint)
    {
        $this->setWebservice($webservice);
        $this->setEndpoint($endpoint);
    }

    /**
     * Set the default repository object that will be used by this query.
     *
     * @param \Cake\Datasource\RepositoryInterface $repository The default repository object to use.
     * @return $this
     */
    public function setRepository(RepositoryInterface $repository)
    {
        assert(
            $repository instanceof Endpoint,
            '`$repository` must be an instance of `' . Endpoint::class . '`.'
        );
        $this->_endpoint = $repository;

        return $this;
    }

    /**
     * Returns the default repository object that will be used by this query,
     * that is, the table that will appear in the from clause.
     *
     * @return \Muffin\Webservice\Model\Endpoint
     */
    public function getRepository(): Endpoint
    {
        return $this->_endpoint;
    }

    /**
     * Mark the query as create
     *
     * @return $this
     */
    public function create()
    {
        $this->action(self::ACTION_CREATE);

        return $this;
    }

    /**
     * Mark the query as update
     *
     * @return $this
     */
    public function update()
    {
        $this->action(self::ACTION_UPDATE);

        return $this;
    }

    /**
     * Mark the query as delete
     *
     * @return $this
     */
    public function delete()
    {
        $this->action(self::ACTION_DELETE);

        return $this;
    }

    /**
     * Returns any data that was stored in the specified clause.
     *
     * - where: QueryExpression, returns null when not set
     * - order: OrderByExpression, returns null when not set
     * - limit: integer or QueryExpression, null when not set
     * - offset: integer or QueryExpression, null when not set
     *
     * @param string $name name of the clause to be returned
     * @return mixed
     */
    public function clause(string $name): mixed
    {
        if (isset($this->_parts[$name])) {
            return $this->_parts[$name];
        }

        return null;
    }

    /**
     * Set the endpoint to be used
     *
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint to use
     * @return $this
     */
    public function setEndpoint(Endpoint $endpoint)
    {
        $this->_endpoint = $endpoint;

        return $this;
    }

    /**
     * Set the endpoint to be used
     *
     * @return \Muffin\Webservice\Model\Endpoint
     */
    public function getEndpoint(): Endpoint
    {
        return $this->_endpoint;
    }

    /**
     * Set the webservice to be used
     *
     * @param \Muffin\Webservice\Webservice\WebserviceInterface $webservice The webservice to use
     * @return $this
     */
    public function setWebservice(WebserviceInterface $webservice)
    {
        $this->_webservice = $webservice;

        return $this;
    }

    /**
     * Get the webservice used
     *
     * @return \Muffin\Webservice\Webservice\WebserviceInterface
     */
    public function getWebservice(): WebserviceInterface
    {
        return $this->_webservice;
    }

    /**
     * Apply conditions to the query
     *
     * @param \Closure|array|string|null $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @param bool $overwrite Whether to replace previous queries.
     * @return $this
     */
    public function where(
        Closure|array|string|null $conditions = null,
        array $types = [],
        bool $overwrite = false
    ) {
        if ($conditions === null) {
            $conditions = [];
        }

        if ($conditions instanceof Closure) {
            $conditions = $conditions($this);
        }

        if (!is_array($conditions)) {
            $conditions = [$conditions];
        }

        $this->_parts['where'] = $overwrite ? $conditions : Hash::merge($this->clause('where'), $conditions);

        return $this;
    }

    /**
     * Charge this query's action
     *
     * @param int $action Action to use
     * @return $this
     */
    public function action(int $action)
    {
        $this->_parts['action'] = $action;

        return $this;
    }

    /**
     * Execute the query
     *
     * @return \Muffin\Webservice\Model\Resource|\Cake\Datasource\ResultSetInterface|int|bool
     */
    public function execute(): Resource|ResultSetInterface|bool|int
    {
        return $this->_webservice->execute($this);
    }

    /**
     * Return a handy representation of the query
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            '(help)' => 'This is a Query object, to get the results execute or iterate it.',
            'action' => $this->clause('action'),
            'conditions' => $this->clause('where'),
            'repository' => $this->getEndpoint(),
            'webservice' => $this->getWebservice(),
        ];
    }
}
