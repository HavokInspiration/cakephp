<?php
namespace Cake\Database;

/**
 * Trait that gathers methods needed to look for and manipulate
 * table name.
 * It also provides a set of method to prefix table names (single or
 * SQL snippets)
 */
trait TableNameAwareTrait
{

    protected $_tableNameSettings = [
        'prefix' => '',
        'tablesNames' => [],
        'quoteStrings' => ['', '']
    ];

    /**
     * Set the table name settings that will be used when prefixing
     * is needed
     *
     * @param array $settings Array of settings to merge to the current settings
     * Keys can be one of the following :
     *
     * - `prefix` - A string representing table name prefix
     * - `tablesNames` - Array of tables names to look for when prefixing
     * - `quoteStrings` - An array where the first value is the opening identifier quote strings
     * and where the second is the closing identifier quote strings
     *
     * @return void
     */
    public function setTableNamesSettings(array $settings = [])
    {
        $this->_tableNameSettings = array_merge($this->_tableNameSettings, $settings);
    }

    /**
     * Prefix a table name or a set of table names / SQL snippets
     *
     * @param string|array $tableNames Table names to be prefixed. An array can be given to prefix
     * multiple table names / SQL snippet at once
     * @param bool $isFromOrJoin Whether the method is called from a from clause or a join clause
     * (more generally whether the first parameter is just a table name or more complex SQL snippet
     * @return string
     */
    public function prefixTableNames($tableNames, $isFromOrJoin = false)
    {
        $prefix = $this->_tableNameSettings['prefix'];

        if (is_string($tableNames) && $this->needsPrefix($tableNames) === true) {
            $tableNames = $this->prefixTableName($tableNames, $isFromOrJoin);
        } elseif (is_array($tableNames)) {
            foreach ($tableNames as $k => $tableName) {
                if (is_string($tableName) && $this->needsPrefix($tableName, $isFromOrJoin) === true) {
                    $tableNames[$k] = $prefix . $tableName;
                } elseif (is_array($tableName) &&
                          isset($tableName['table']) &&
                          is_string($tableName['table']) &&
                          $this->needsPrefix($tableName['table'], $isFromOrJoin) === true
                ) {
                    $tableNames[$k]['table'] = $this->prefixTableName($tableName['table'], $isFromOrJoin);
                }
            }
        }

        return $tableNames;
    }

    /**
     * Prefix a table name
     *
     * @param string $tableName Table name to be prefixed
     * @param bool $isFromOrJoin Whether the method is called from a from clause or a join clause
     * (more generally whether the first parameter is just a table name or more complex SQL snippet
     * @return string
     */
    public function prefixTableName($tableName, $isFromOrJoin = false)
    {
        if ($this->needsPrefix($tableName, $isFromOrJoin) === true) {
            $tableName = $this->_tableNameSettings['prefix'] . $tableName;
        }
        return $tableName;
    }

    /**
     * Prefix table names in a SQL snippet (rather than in a single table name)
     *
     * @param string $fieldName SQL snippet in which table name are to be found and prefixed
     * @return string
     */
    public function prefixFieldName($fieldName)
    {
        $prefix = $this->_tableNameSettings['prefix'];
        $tablesNames = $this->_tableNameSettings['tablesNames'];
        $quoteStrings = $this->_tableNameSettings['quoteStrings'];

        $prefixedFieldName = $fieldName;
        if ($this->needsPrefix($prefixedFieldName, false) && is_string($fieldName) && !empty($tablesNames)) {
            $lookAhead = implode('|', $tablesNames);
            $tableNamePattern = '([\w-]+)';
            $replacePattern = $prefix . '$1$2';

            list($startQuote, $endQuote) = $quoteStrings;

            if (!empty($startQuote) && !empty($endQuote)) {
                $lookAhead = $startQuote . '?' . implode($endQuote . '?|' . $startQuote . '?', $tablesNames) . $endQuote . '?';
                $tableNamePattern = '[' . $startQuote . ']?' . $tableNamePattern . '[' . $endQuote . ']?';
                $replacePattern = $startQuote . $prefix . '$1' . $endQuote . '$2';
            }

            $wordPattern = $this->buildWordPattern($quoteStrings);
            $pattern = '/(?=(?:' . $lookAhead . '))' . $tableNamePattern . '(\.' . $wordPattern . '|\.\*)/';
            $prefixedFieldName = preg_replace($pattern, $replacePattern, $prefixedFieldName);
        }

        return $prefixedFieldName;
    }

    /**
     * Returns the raw table name (meaning without its prefix
     * if the parameter is a prefixed table name
     *
     * @param string $tableName Table name to "un-prefix"
     * @return string
     */
    public function rawTableName($tableName)
    {
        $prefix = $this->_tableNameSettings['prefix'];
        if ($prefix !== '' && strpos($tableName, $prefix) === 0) {
            $tableName = substr($tableName, strlen($prefix));
        }

        return $tableName;
    }

    /**
     * Checks whether $tableName is prefixed or if the SQL snippet it represents
     * contains a table name that is prefixed
     *
     * @param string $tableName Table or field name or SQL snippet
     * @param bool $isFromOrJoin Whether the method is called from a from clause or a join clause
     * (more generally whether the first parameter is just a table name or more complex SQL snippet
     * @return bool
     */
    public function isTableNamePrefixed($tableName, $isFromOrJoin = false)
    {
        $prefix = $this->_tableNameSettings['prefix'];

        if ($prefix === '' ||
            strpos($tableName, $prefix) === false ||
            $tableName === $prefix ||
            empty($this->_tableNameSettings['tablesNames'])
        ) {
            return false;
        }

        if ($isFromOrJoin === true) {
            $expectedOffset = 0;

            $startQuote = $this->_tableNameSettings['quoteStrings'][0];
            if (!empty($startQuote) && strpos($tableName, $startQuote) === 0) {
                $expectedOffset = 1;
            }
            return strpos($tableName, $prefix) === $expectedOffset;
        }

        if (strpos($tableName, $prefix) !== false) {
            $lookAhead = $prefix . implode('|', $this->_tableNameSettings['tablesNames']);
            list($startQuote, $endQuote) = $this->_tableNameSettings['quoteStrings'];
            if (!empty($startQuote) && strpos($tableName, $startQuote) === 0) {
                $lookAhead = $startQuote . '?' .
                    implode($endQuote . '?|' . $startQuote . '?', $this->_tableNameSettings['tablesNames']) .
                    $endQuote . '?';
            }

            $wordPattern = $this->buildWordPattern();

            $pattern = '/';
            if (isset($lookAhead)) {
                $pattern .= '(?=(?:' . $lookAhead . '))(' . $wordPattern . ')';
            } else {
                $pattern .= '(' . $prefix . $wordPattern . ')';
            }

            if (strpos($tableName, '.') !== false) {
                $pattern .= '(\.' . $wordPattern . ')';
            }

            $pattern .= '/';
            return preg_match_all($pattern, $tableName) > 0;
        }

        return false;
    }

    /**
     * Checks whether $name is or contain (e.g. 'table.field') a table name.
     *
     * @param string $tableName Table or field name or SQL snippet
     * @return bool
     */
    public function hasTableName($tableName)
    {
        if (is_string($tableName) && !empty($this->_tableNameSettings['tablesNames'])) {
            $lookAhead = implode('|', $this->_tableNameSettings['tablesNames']);
            list($startQuote, $endQuote) = $this->_tableNameSettings['quoteStrings'];
            if (!empty($startQuote) && strpos($tableName, $startQuote) === 0) {
                $lookAhead = $startQuote . '?' . implode($endQuote . '?|' . $startQuote . '?', $this->_tableNameSettings['tablesNames']) . $endQuote . '?';
            }

            $wordPattern = $this->buildWordPattern();
            $pattern = '/(?=(?:' . $lookAhead . '))(' . $wordPattern . ')';

            if (strpos($tableName, '.') !== false) {
                $pattern .= '(\.' . $wordPattern . '|\.\*)';
            }

            $pattern .= '/';
            return isset($tableNames[$tableName]) || preg_match_all($pattern, $tableName) > 0;
        }
        return false;
    }

    /**
     * Shortcut method that will consecutively do a hasTableName and a isTableNamePrefixed
     *
     * @param string|array|ExpressionInterface $field String to check for table name
     * @return bool
     */
    public function needsPrefix($field)
    {
        $hasTableName = $this->hasTableName($field);

        if ($hasTableName === false) {
            return false;
        }

        $isTableNamePrefixed = $this->isTableNamePrefixed($field);

        return $isTableNamePrefixed === false;
    }

    /**
     * Utility method used to built the word pattern for table name
     * detection
     *
     * @return bool
     */
    protected function buildWordPattern()
    {
        $wordPattern = '[\w-]+';
        list($startQuote, $endQuote) = $this->_tableNameSettings['quoteStrings'];
        if (!empty($startQuote) && !empty($endQuote)) {
            if ($startQuote === $endQuote) {
                $wordPattern = '[\w' . $startQuote . '-]+';
            } else {
                $wordPattern = '[\w' . $startQuote . $endQuote . '-]+';
            }
        }

        return $wordPattern;
    }
}
