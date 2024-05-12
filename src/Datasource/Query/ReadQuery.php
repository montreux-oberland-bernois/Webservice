<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Query;

use ArrayObject;
use Cake\Collection\Iterator\MapReduce;
use Cake\Database\ExpressionInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryCacher;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\ResultSetDecorator;
use Cake\Datasource\ResultSetInterface;
use Cake\Utility\Hash;
use Closure;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Traversable;

/**
 * @template TKey
 * @template-covariant TValue
 * @template-implements \IteratorAggregate<TKey, TValue>
 */
class ReadQuery extends Query implements IteratorAggregate, JsonSerializable, QueryInterface
{
    /**
     * Indicates that the operation should append to the list
     *
     * @var int
     */
    public const APPEND = 0;

    /**
     * Indicates that the operation should prepend to the list
     *
     * @var int
     */
    public const PREPEND = 1;

    /**
     * Indicates that the operation should overwrite the list
     *
     * @var bool
     */
    public const OVERWRITE = true;

    /**
     * True if the beforeFind event has already been triggered for this query
     *
     * @var bool
     */
    protected bool $_beforeFindFired = false;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'select' => [],
        'where' => [],
        'order' => [],
        'action' => self::ACTION_READ,
    ];

    /**
     * Holds any custom options passed using applyOptions that could not be processed
     * by any method in this class.
     *
     * @var array
     */
    protected array $_options = [];

    /**
     * The result from the webservice
     *
     * @var \Muffin\Webservice\Model\Resource|\Cake\Datasource\ResultSetInterface|int|bool|null
     */
    protected Resource|ResultSetInterface|int|bool|null $_results = null;

    /**
     * Instance of a endpoint object this query is bound to
     *
     * @var \Muffin\Webservice\Model\Endpoint
     */
    protected Endpoint $_endpoint;

    /**
     * List of map-reduce routines that should be applied over the query
     * result
     *
     * @var array
     */
    protected array $_mapReduce = [];

    /**
     * List of formatter classes or callbacks that will post-process the
     * results when fetched
     *
     * @var array<\Closure>
     */
    protected array $_formatters = [];

    /**
     * A query cacher instance if this query has caching enabled.
     *
     * @var \Cake\Datasource\QueryCacher|null
     */
    protected ?QueryCacher $_cache = null;

    /**
     * Returns a key => value array where both the key and value are the `$field`.
     *
     * @param string $field The field to alias.
     * @param string|null $alias Not being used
     * @return array<string, string>
     */
    public function aliasField(string $field, ?string $alias = null): array
    {
        return [$field => $field];
    }

    /**
     * @inheritDoc
     */
    public function aliasFields(array $fields, ?string $defaultAlias = null): array
    {
        $aliased = [];
        foreach ($fields as $alias => $field) {
            if (is_numeric($alias) && is_string($field)) {
                $aliased += $this->aliasField($field, $defaultAlias);
                continue;
            }
            $aliased[$alias] = $field;
        }

        return $aliased;
    }

    /**
     * Fetch the results for this query.
     *
     * Will return either the results set through setResult(), or execute this query
     * and return the ResultSetDecorator object ready for streaming of results.
     *
     * ResultSetDecorator is a traversable object that implements the methods found
     * on Cake\Collection\Collection.
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    public function all(): ResultSetInterface
    {
        if (is_iterable($this->_results)) {
            if (!$this->_results instanceof ResultSetInterface) {
                $this->_results = $this->decorateResults($this->_results);
            }

            return $this->_results;
        }

        /** @psalm-suppress InternalMethod Could not find a better way apart from implementing it as a custom class **/
        $results = $this->_cache?->fetch($this);
        if ($results === null) {
            $res = $this->execute();

            if (!is_iterable($res)) {
                return $this->_results = new ResultSet([$res], 1);
            }

            $results = $this->decorateResults($res);
            /** @psalm-suppress InternalMethod Could not find a better way apart from implementing it as a custom class **/
            $this->_cache?->store($this, $results);
        }
        $this->_results = $results;

        return $this->_results;
    }

    /**
     * Executes this query and returns a results iterator. This function is required
     * for implementing the IteratorAggregate interface and allows the query to be
     * iterated without having to call execute() manually, thus making it look like
     * a result set instead of the query itself.
     *
     * @return \Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->all();
    }

    /**
     * Returns an array representation of the results after executing the query.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->all()->toArray();
    }

    /**
     * Executes the query and converts the result set into JSON.
     *
     * Part of JsonSerializable interface.
     *
     * @return \Cake\Datasource\ResultSetInterface The data to convert to JSON.
     */
    public function jsonSerialize(): ResultSetInterface
    {
        return $this->all();
    }

    /**
     * Adds fields to be selected from _source.
     *
     * Calling this function multiple times will append more fields to the
     * list of fields to be selected from _source.
     *
     * If `true` is passed in the second argument, any previous selections
     * will be overwritten with the list passed in the first argument.
     *
     * @param \Cake\Database\ExpressionInterface|\Closure|array|string|float|int $fields The list of fields to select from _source.
     * @param bool $overwrite Whether or not to replace previous selections.
     * @return $this
     */
    public function select(ExpressionInterface|Closure|array|string|int|float $fields, bool $overwrite = false)
    {
        if (!is_string($fields) && is_callable($fields)) {
            $fields = $fields($this);
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if ($overwrite) {
            $this->_parts['select'] = $fields;
        } else {
            $this->_parts['select'] = array_merge($this->_parts['select'], $fields);
        }

        return $this;
    }

    /**
     * Add AND conditions to the query
     *
     * @param array|string $conditions The conditions to add with AND.
     * @param array $types associative array of type names used to bind values to query
     * @return $this
     * @see \Cake\Database\Query::where()
     * @see \Cake\Database\Type
     */
    public function andWhere(array|string $conditions, array $types = [])
    {
        $this->where($conditions, $types);

        return $this;
    }

    /**
     * Adds a single or multiple fields to be used in the ORDER clause for this query.
     * Fields can be passed as an array of strings, array of expression
     * objects, a single expression or a single string.
     *
     * If an array is passed, keys will be used as the field itself and the value will
     * represent the order in which such field should be ordered. When called multiple
     * times with the same fields as key, the last order definition will prevail over
     * the others.
     *
     * By default this function will append any passed argument to the list of fields
     * to be selected, unless the second argument is set to true.
     *
     * @param \Closure|array|string $fields The field configuration for the order by clause
     * @param bool $overwrite Whether to overwrite the existing conditions
     * @return $this
     */
    public function orderBy(Closure|array|string $fields, bool $overwrite = false)
    {
        if ($fields instanceof Closure) {
            $fields = $fields($this);
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        $this->_parts['order'] = $overwrite ? $fields : Hash::merge($this->clause('order'), $fields);

        return $this;
    }

    /**
     * @deprecated version 4.0.0 Use orderBy() instead.
     * @param \Closure|array|string $fields fields to be added to the list
     * @param bool $overwrite whether to reset order with field list or not
     * @return $this
     */
    public function order(Closure|array|string $fields, bool $overwrite = false)
    {
        return $this->orderBy($fields, $overwrite);
    }

    /**
     * Set the page of results you want.
     *
     * This method provides an easier to use interface to set the limit + offset
     * in the record set you want as results. If empty the limit will default to
     * the existing limit clause, and if that too is empty, then `25` will be used.
     *
     * Pages should start at 1.
     *
     * @param int $num The page number you want.
     * @param int|null $limit The number of rows you want in the page. If null
     *  the current limit clause will be used.
     * @return $this
     */
    public function page(int $num, ?int $limit = null)
    {
        if ($num < 1) {
            throw new InvalidArgumentException('Pages must start at 1.');
        }

        if ($limit !== null) {
            $this->limit($limit);
        }

        $this->_parts['page'] = $num;

        return $this;
    }

    /**
     * Sets the number of records that should be retrieved from the webservice,
     * accepts an integer or an expression object that evaluates to an integer.
     * In some webservices, this operation might not be supported or will require
     * the query to be transformed in order to limit the result set size.
     *
     * ### Examples
     *
     * ```
     * $query->limit(10) // generates LIMIT 10
     * ```
     *
     * @param ?int $limit number of records to be returned
     * @return $this
     */
    public function limit(?int $limit)
    {
        $this->_parts['limit'] = $limit;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offset(?int $offset)
    {
        $this->_parts['offset'] = $offset;

        return $this;
    }

    /**
     * Returns the total amount of results for this query
     *
     * @return int
     */
    public function count(): int
    {
        if ($this->_results === null) {
            $this->execute();
        }

        if ($this->_results instanceof ResultSet) {
            return (int)$this->_results->total();
        }
        if ($this->_results instanceof ResultSetInterface) {
            return $this->_results->count();
        }
        if ($this->_results === null) {
            return 0;
        }

        // There is a single integer or boolean value
        return 1;
    }

    /**
     * Apply custom finds to against an existing query object.
     *
     * Allows custom find methods to be combined and applied to each other.
     *
     * ```
     * $repository->find('all')->find('recent');
     * ```
     *
     * The above is an example of stacking multiple finder methods onto
     * a single query.
     *
     * @param string $finder The finder method to use.
     * @param mixed ...$args Arguments that match up to finder-specific parameters
     * @return static Returns a modified query.
     * @psalm-suppress MoreSpecificReturnType Couldn't get it to work with the interface and has no impact
     */
    public function find(string $finder, mixed ...$args): static
    {
        /** @psalm-suppress LessSpecificReturnStatement Couldn't get it to work with the interface and has no impact **/
        return $this->_endpoint->callFinder($finder, $this, $args); /* @phpstan-ignore-line */
    }

    /**
     * Returns the first result out of executing this query, if the query has not been
     * executed before, it will set the limit clause to 1 for performance reasons.
     *
     * ### Example:
     *
     * ```
     * $singleUser = $query->first();
     * ```
     *
     * @return mixed the first result from the ResultSet
     */
    public function first(): mixed
    {
        if ($this->_dirty) {
            $this->limit(1);
        }

        return $this->all()->first();
    }

    /**
     * Get the first result from the executing query or raise an exception.
     *
     * @return mixed The first result from the ResultSet.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException|\Exception When there is no first record.
     */
    public function firstOrFail(): mixed
    {
        $entity = $this->first();
        if ($entity) {
            return $entity;
        }

        throw new RecordNotFoundException(sprintf(
            'Record not found in endpoint "%s"',
            $this->_endpoint->getName()
        ));
    }

    /**
     * Populates or adds parts to current query clauses using an array.
     * This is handy for passing all query clauses at once.
     *
     * @param array $options the options to be applied
     * @return $this This object
     */
    public function applyOptions(array $options)
    {
        $valid = [
            'select' => 'select',
            'fields' => 'select',
            'conditions' => 'where',
            'where' => 'where',
            'order' => 'orderBy',
            'orderBy' => 'orderBy',
            'limit' => 'limit',
            'offset' => 'offset',
            'page' => 'page',
        ];

        ksort($options);
        foreach ($options as $option => $values) {
            if (isset($valid[$option], $values)) {
                $this->{$valid[$option]}($values);

                unset($options[$option]);
            }
        }

        $this->_options = Hash::merge($this->_options, $options);

        return $this;
    }

    /**
     * Returns an array with the custom options that were applied to this query
     * and that were not already processed by another method in this class.
     *
     * ### Example:
     *
     * ```
     *  $query->applyOptions(['doABarrelRoll' => true, 'fields' => ['id', 'name']);
     *  $query->getOptions(); // Returns ['doABarrelRoll' => true]
     * ```
     *
     * @see \Cake\Datasource\QueryInterface::applyOptions() to read about the options that will
     * be processed by this class and not returned by this function
     * @return array
     * @see applyOptions()
     */
    public function getOptions(): array
    {
        return $this->_options;
    }

    /**
     * Trigger the beforeFind event on the query's repository object.
     *
     * Will not trigger more than once, and only for select queries.
     *
     * @return void
     */
    public function triggerBeforeFind(): void
    {
        if (!$this->_beforeFindFired) {
            /** @var \Muffin\Webservice\Model\Endpoint $endpoint */
            $endpoint = $this->getRepository();
            $this->_beforeFindFired = true;
            $endpoint->dispatchEvent('Model.beforeFind', [
                $this,
                new ArrayObject($this->_options),
            ]);
        }
    }

    /**
     * Execute the query
     *
     * @return \Muffin\Webservice\Model\Resource|\Cake\Datasource\ResultSetInterface|int|bool
     */
    public function execute(): bool|int|Resource|ResultSetInterface
    {
        $this->triggerBeforeFind();

        if ($this->_results !== null) {
            $decorator = $this->decoratorClass();
            if (is_iterable($this->_results) && !($this->_results instanceof $decorator)) {
                $this->_results = new $decorator($this->_results);
            }

            return $this->_results;
        }

        return $this->_results = $this->_webservice->execute($this);
    }

    /**
     * Returns the name of the class to be used for decorating results
     *
     * @return class-string<\Cake\Datasource\ResultSetInterface>
     */
    protected function decoratorClass(): string
    {
        return ResultSetDecorator::class;
    }

    /**
     * Decorates the results iterator with MapReduce routines and formatters
     *
     * @param iterable $result Original results
     * @return \Cake\Datasource\ResultSetInterface
     */
    protected function decorateResults(iterable $result): ResultSetInterface
    {
        $decorator = $this->decoratorClass();

        if (!empty($this->_mapReduce)) {
            foreach ($this->_mapReduce as $functions) {
                $result = new MapReduce($result, $functions['mapper'], $functions['reducer']);
            }
            $result = new $decorator($result);
        }

        if (!($result instanceof ResultSetInterface)) {
            $result = new $decorator($result);
        }

        if (!empty($this->_formatters)) {
            foreach ($this->_formatters as $formatter) {
                $result = $formatter($result, $this);
            }

            if (!($result instanceof ResultSetInterface)) {
                $result = new $decorator($result);
            }
        }

        return $result;
    }

    /**
     * Register a new MapReduce routine to be executed on top of the database results
     *
     * The MapReduce routing will only be run when the query is executed and the first
     * result is attempted to be fetched.
     *
     * If the third argument is set to true, it will erase previous map reducers
     * and replace it with the arguments passed.
     *
     * @param \Closure|null $mapper The mapper function
     * @param \Closure|null $reducer The reducing function
     * @param bool $overwrite Set to true to overwrite existing map + reduce functions.
     * @return $this
     * @see \Cake\Collection\Iterator\MapReduce for details on how to use emit data to the map reducer.
     */
    public function mapReduce(?Closure $mapper = null, ?Closure $reducer = null, bool $overwrite = false)
    {
        if ($overwrite) {
            $this->_mapReduce = [];
        }
        if ($mapper === null) {
            if (!$overwrite) {
                throw new InvalidArgumentException('$mapper can be null only when $overwrite is true.');
            }

            return $this;
        }
        $this->_mapReduce[] = compact('mapper', 'reducer');

        return $this;
    }

    /**
     * Registers a new formatter callback function that is to be executed when trying
     * to fetch the results from the database.
     *
     * If the second argument is set to true, it will erase previous formatters
     * and replace them with the passed first argument.
     *
     * Callbacks are required to return an iterator object, which will be used as
     * the return value for this query's result. Formatter functions are applied
     * after all the `MapReduce` routines for this query have been executed.
     *
     * Formatting callbacks will receive two arguments, the first one being an object
     * implementing `\Cake\Collection\CollectionInterface`, that can be traversed and
     * modified at will. The second one being the query instance on which the formatter
     * callback is being applied.
     *
     * ### Examples:
     *
     * Return all results from the table indexed by id:
     *
     * ```
     * $query->select(['id', 'name'])->formatResults(function ($results) {
     *     return $results->indexBy('id');
     * });
     * ```
     *
     * Add a new column to the ResultSet:
     *
     * ```
     * $query->select(['name', 'birth_date'])->formatResults(function ($results) {
     *     return $results->map(function ($row) {
     *         $row['age'] = $row['birth_date']->diff(new DateTime)->y;
     *
     *         return $row;
     *     });
     * });
     * ```
     *
     * @param \Closure|null $formatter The formatting function
     * @param int|bool $mode Whether to overwrite, append or prepend the formatter.
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function formatResults(?Closure $formatter = null, int|bool $mode = self::APPEND)
    {
        if ($mode === self::OVERWRITE) {
            $this->_formatters = [];
        }
        if ($formatter === null) {
            /** @psalm-suppress RedundantCondition */
            if ($mode !== self::OVERWRITE) {
                throw new InvalidArgumentException('$formatter can be null only when $mode is overwrite.');
            }

            return $this;
        }

        if ($mode === self::PREPEND) {
            array_unshift($this->_formatters, $formatter);

            return $this;
        }

        $this->_formatters[] = $formatter;

        return $this;
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
            'formatters' => $this->_formatters,
            'offset' => $this->clause('offset'),
            'page' => $this->clause('page'),
            'limit' => $this->clause('limit'),
            'sort' => $this->clause('order'),
            'extraOptions' => $this->getOptions(),
            'conditions' => $this->clause('where'),
            'repository' => $this->getEndpoint(),
            'webservice' => $this->getWebservice(),
        ];
    }
}
