<?php
declare(strict_types=1);

namespace Muffin\Webservice\Model;

use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Datasource\Locator\AbstractLocator;
use Cake\Datasource\RepositoryInterface;
use Cake\Utility\Inflector;
use Muffin\Webservice\Datasource\Connection;
use function Cake\Core\pluginSplit;

/**
 * Class EndpointLocator
 */
class EndpointLocator extends AbstractLocator
{
    /**
     * Set an Endpoint instance to the locator.
     *
     * @param string $alias The alias to set.
     * @param \Muffin\Webservice\Model\Endpoint $repository The repository to set.
     * @return \Muffin\Webservice\Model\Endpoint
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress MoreSpecificImplementedParamType Not a nice solution, but in this plugin, we only support Endpoints
     */
    public function set(string $alias, RepositoryInterface $repository): Endpoint
    {
        return $this->instances[$alias] = $repository;
    }

    /**
     * Get a endpoint instance from the locator.
     *
     * @param string $alias The alias name you want to get.
     * @param array $options The options you want to build the endpoint with.
     * @return \Muffin\Webservice\Model\Endpoint
     * @throws \RuntimeException If the registry alias is already in use.
     */
    public function get(string $alias, array $options = []): Endpoint
    {
        $parentRes = parent::get($alias, $options);

        assert(
            $parentRes instanceof Endpoint,
            'The repository found is not of type Endpoint, but a different type implementing RepositoryInterface'
        );

        return $parentRes;
    }

    /**
     * Wrapper for creating endpoint instances
     *
     * @param string $alias Endpoint alias.
     * @param array $options The alias to check for.
     * @return \Muffin\Webservice\Model\Endpoint
     */
    protected function createInstance(string $alias, array $options): Endpoint
    {
        [, $classAlias] = pluginSplit($alias);
        $options = ['alias' => $classAlias] + $options;

        if (empty($options['className'])) {
            $options['className'] = Inflector::camelize($alias);
        }
        $className = App::className($options['className'], 'Model/Endpoint', 'Endpoint');
        if ($className) {
            $options['className'] = $className;
        } else {
            if (!isset($options['endpoint']) && !str_contains($options['className'], '\\')) {
                [, $endpoint] = pluginSplit($options['className']);
                $options['endpoint'] = Inflector::underscore($endpoint);
            }
            $options['className'] = Endpoint::class;
        }

        if (empty($options['connection'])) {
            if ($options['className'] !== Endpoint::class) {
                $connectionName = $options['className']::defaultConnectionName();
            } else {
                if (!str_contains($alias, '.')) {
                    $connectionName = 'webservice';
                } else {
                    /** @psalm-suppress PossiblyNullArgument Not clean, but cannot happen with incorrect configuration and was not a problem before **/
                    $pluginParts = explode('/', pluginSplit($alias)[0]); /* @phpstan-ignore-line */
                    $connectionName = Inflector::underscore(end($pluginParts));
                }
            }

            $options['connection'] = $this->getConnection($connectionName);
        } elseif (is_string($options['connection'])) {
            $options['connection'] = $this->getConnection($options['connection']);
        }

        $options['registryAlias'] = $alias;

        /** @psalm-var class-string<\Muffin\Webservice\Model\Endpoint> $className */
        $className = $options['className'];

        return new $className($options);
    }

    /**
     * Get connection instance.
     *
     * @param string $connectionName Connection name.
     * @return \Muffin\Webservice\Datasource\Connection
     */
    protected function getConnection(string $connectionName): Connection
    {
        try {
            /** @var \Muffin\Webservice\Datasource\Connection */
            return ConnectionManager::get($connectionName);
        } catch (MissingDatasourceConfigException $e) {
            $message = $e->getMessage()
                . ' You can override Endpoint::defaultConnectionName() to return the connection name you want.';

            throw new MissingDatasourceConfigException($message, $e->getCode(), $e->getPrevious());
        }
    }
}
