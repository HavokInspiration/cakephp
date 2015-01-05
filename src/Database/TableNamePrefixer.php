<?php
namespace Cake\Database;

use Cake\Database\Expression\FieldInterface;
use Cake\Database\ExpressionInterface;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\OrderByExpression;
use Cake\Database\Expression\QueryExpression;

/**
 * Contains all the logic related to prefixing table names in a Query object
 */
class TableNamePrefixer
{

    use TableNameAwareTrait;

    /**
     * The Query instance used of the current query
     *
     * @var \Cake\Database\Query
     */
    protected $_query;

    /**
     * The ValueBinder instance used in the current query
     *
     * @var \Cake\Database\ValueBinder
     */
    protected $_binder;

    /**
     * List of the query parts to prefix
     *
     * @var array
     */
    protected $_partsToPrefix = ['select', 'from', 'join', 'group', 'update', 'insert'];

    /**
     * Iterates over each of the clauses in a query looking for table names and
     * prefix them
     *
     * @param \Cake\Database\Query $query The query to have its table names prefixed
     * @return \Cake\Database\Query
     */
    public function prefix(Query $query)
    {
        $this->_binder = $query->valueBinder();
        $this->_query = $query;
        $query->valueBinder(false);

        $this->setTableNamesSettings([
            'prefix' => $this->getPrefix(),
            'tablesNames' => $query->tablesNames,
            'quoteStrings' => $this->getQuoteStrings()
        ]);

        $this->_prefixParts();

        $query->traverseExpressions([$this, 'prefixExpression']);
        $query->valueBinder($this->_binder);
        return $query;
    }

    /**
     * Prefixes table name or field name inside Expression objects
     *
     * @param \Cake\Database\ExpressionInterface $expression The expression object to traverse and prefix
     * @return void
     */
    public function prefixExpression($expression)
    {
        if ($expression instanceof FieldInterface) {
            $this->_prefixFieldInterface($expression);
            return;
        }

        if ($expression instanceof OrderByExpression) {
            $this->_prefixOrderByExpression($expression);
            return;
        }

        if ($expression instanceof IdentifierExpression) {
            $this->_prefixIdentifierExpression($expression);
        }

        if ($expression instanceof QueryExpression) {
            $this->_prefixQueryExpression($expression);
            return;
        }
    }

    /**
     * Prefix Expressions implementing the FieldInterface
     *
     * @param \Cake\Database\Expression\FieldInterface $expression The expression to prefix
     * @return void
     */
    protected function _prefixFieldInterface(FieldInterface $expression)
    {
        $field = $expression->getField();

        if (
            is_string($field) &&
            strpos($field, '.') !== false &&
            $this->needsPrefix($field, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings())
        ) {
            $field = $this->prefixFieldName($field, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings());
            $expression->setField($field);
        }
    }

    /**
     * Prefix OrderByExpression object
     *
     * @param \Cake\Database\Expression\OrderByExpression $expression The expression to prefix
     * @return void
     */
    protected function _prefixOrderByExpression(OrderByExpression $expression)
    {
        $query = $this->_query;
        $binder = $this->_binder;
        $prefix = $this->getPrefix();

        $expression->iterateParts(function ($condition, &$key) use ($query, $binder, $prefix) {
            if ($this->needsPrefix($key, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings())) {
                $key = $this->prefixFieldName($key, $prefix, $this->_query->tablesNames, $this->getQuoteStrings());

                if ($key instanceof ExpressionInterface) {
                    $key = $key->sql($binder);
                }
            }

            return $condition;
        });
    }

    /**
     * Prefix IdentifierExpression object
     *
     * @param \Cake\Database\Expression\IdentifierExpression $expression The expression to prefix
     * @return void
     */
    protected function _prefixIdentifierExpression(IdentifierExpression $expression)
    {
        $identifier = $expression->getIdentifier();
        $prefix = $this->getPrefix();

        if (
            is_string($identifier) &&
            strpos($identifier, '.') !== false &&
            $this->needsPrefix($identifier, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings())
        ) {
            $identifier = $this->prefixFieldName($identifier, $prefix, $this->_query->tablesNames, $this->getQuoteStrings());
            $expression->setIdentifier($identifier);
        }
    }

    /**
     * Prefix QueryExpression object
     *
     * @param \Cake\Database\Expression\QueryExpression $expression The expression to prefix
     * @return void
     */
    protected function _prefixQueryExpression(QueryExpression $expression)
    {
        $query = $this->_query;
        $prefix = $this->getPrefix();
        $expression->iterateParts(function ($condition, $key) use ($query, $prefix) {
            if (
                is_string($condition) &&
                $this->needsPrefix($condition, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings())
            ) {
                $condition = $this->prefixFieldName($condition, $prefix, $query->tablesNames, $this->getQuoteStrings());
            }
            return $condition;
        });
    }

    /**
     * Quotes all identifiers in each of the clauses of a query
     *
     * @return void
     */
    protected function _prefixParts()
    {
        foreach ($this->_partsToPrefix as $part) {
            $contents = $this->_query->clause($part);

            if (empty($contents)) {
                continue;
            }

            $methodName = '_prefix' . ucfirst($part) . 'Parts';
            if (method_exists($this, $methodName)) {
                $this->{$methodName}($contents);
            }
        }
    }

    /**
     * Prefixes the table name in the "update" clause
     *
     * @param array $parts the parts of the query to prefix
     * @return array
     */
    protected function _prefixInsertParts($parts)
    {
        $parts = $this->prefixTableNames($parts, $this->getPrefix(), $this->getQuoteStrings());
        $this->_query->into($parts[0]);
    }

    /**
     * Prefixes the table name in the "update" clause
     *
     * @param array $parts the parts of the query to prefix
     * @return array
     */
    protected function _prefixUpdateParts($parts)
    {
        $parts = $this->prefixTableNames($parts, $this->getPrefix(), $this->getQuoteStrings());
        $this->_query->update($parts[0]);
    }

    /**
     * Prefixes the table name in clause of the Query having a basic forms
     *
     * @param array $parts the parts of the query to prefix
     * @return array
     */
    protected function _prefixFromParts($parts)
    {
        $parts = $this->prefixTableNames($parts, true);
        $this->_query->from($parts, true);
    }

    /**
     * Prefixes the table names for the "select" clause
     *
     * @param array $parts The parts of the query to prefix
     *
     * @return void
     */
    protected function _prefixSelectParts($parts)
    {
        if (!empty($parts)) {
            foreach ($parts as $alias => $part) {
                if ($this->hasTableName($part, $this->_query->tablesNames, $this->getQuoteStrings()) === true) {
                    $parts[$alias] = $this->prefixFieldName($part, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings());
                }
            }

            $this->_query->select($parts, true);
        }
    }

    /**
     * Prefixes the table names for the "join" clause
     *
     * @param array $parts The parts of the query to prefix
     *
     * @return void
     */
    protected function _prefixJoinParts($parts)
    {
        if (!empty($parts)) {
            foreach ($parts as $alias => $join) {
                $join['table'] = $this->prefixTableNames($join['table'], true);

                $parts[$alias] = $join;
            }

            $this->_query->join($parts, [], true);
        }
    }

    /**
     * Prefixes the table names for the "group" clause
     *
     * @param array $parts The parts of the query to prefix
     *
     * @return void
     */
    protected function _prefixGroupParts($parts)
    {
        if (!empty($parts)) {
            foreach ($parts as $key => $part) {
                if ($this->needsPrefix($part, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings())) {
                    $parts[$key] = $this->prefixFieldName($part, $this->getPrefix(), $this->_query->tablesNames, $this->getQuoteStrings());
                }
            }
        }

        $this->_query->group($parts, true);
    }

    /**
     * Retrieve the connection prefix using the Query instance
     * stored as a property
     *
     * @return string The connection prefix (or an empty string if no
     * prefix is configured)
     */
    protected function getPrefix()
    {
        return $this->_query->connection()->getPrefix();
    }

    /**
     * Retrieve the strings used to quote identifiers for the driver
     * the current connection implements
     *
     * @return array Array with both quote strings
     */
    protected function getQuoteStrings()
    {
        return $this->_query->connection()->driver()->getQuoteStrings();
    }

}
