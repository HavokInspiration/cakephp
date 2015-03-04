<?php
namespace Cake\Database;
use RuntimeException;

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
        if (isset($settings['prefix'])) {
            $this->_tableNameSettings['prefix'] = $settings['prefix'];
        }

        if (isset($settings['quoteStrings']) && is_array($settings['quoteStrings']) && count($settings['quoteStrings']) === 2) {
            $this->_tableNameSettings['quoteStrings'] = $settings['quoteStrings'];
        }

        if (isset($settings['tablesNames']) && is_array($settings['tablesNames'])) {
            if (empty($settings['tablesNames'])) {
                $settings['tablesNames'] = [];
            } else {
                $settings['tablesNames'] = array_map([$this, 'rawTableName'], $settings['tablesNames']);
                $this->_tableNameSettings['tablesNames'] += $settings['tablesNames'];
            }
        }
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
        if (is_string($tableNames) && $this->isTableNamePrefixed($tableNames, $isFromOrJoin) === false) {
            $tableNames = $this->prefixTableName($tableNames, $isFromOrJoin);
        } elseif (is_array($tableNames)) {
            foreach ($tableNames as $k => $tableName) {
                if (is_string($tableName) && $this->isTableNamePrefixed($tableName, $isFromOrJoin) === false) {
                    $tableNames[$k] = $this->prefixTableName($tableName, $isFromOrJoin);
                } elseif (is_array($tableName) &&
                    isset($tableName['table']) &&
                    is_string($tableName['table']) &&
                    $this->isTableNamePrefixed($tableName['table'], $isFromOrJoin) === false
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
        if ($this->isTableNamePrefixed($tableName, $isFromOrJoin) === false) {
            if (!empty($this->_tableNameSettings['quoteStrings'][0]) &&
                strpos($tableName, $this->_tableNameSettings['quoteStrings'][0]) === 0
            ) {
                $tableName = $tableName[0] . $this->_tableNameSettings['prefix'] . substr($tableName, 1);
            } else {
                $tableName = $this->_tableNameSettings['prefix'] . $tableName;
            }
        }
        return $tableName;
    }

    /**
     * Prefix table names in a SQL snippet (rather than in a single table name)
     * As opposed to prefixTableNames() and prefixTableName(), this method needs to work
     * with a regular expression as it is meant to prefix table name in expressions that
     * could contain table aliases.
     * This means that if you are to use this method, you must absolutely pass what are
     * the table names to look for and prefix with the method setTableNameSettings()
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
        if (is_string($fieldName) && !empty($tablesNames) && $this->isTableNamePrefixed($fieldName) === false) {
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
        $quoteStrings = $this->_tableNameSettings['quoteStrings'];

        $offsetExpected = !empty($quoteStrings[0]) && strpos($tableName, $quoteStrings[0]) === 0 ? strlen($quoteStrings[0]) : 0;
        if ($prefix !== '' && strpos($tableName, $prefix) === $offsetExpected) {
            $tableName = str_replace($prefix, "", $tableName);
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
            $tableName === $prefix
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

        if (strpos($tableName, $prefix) !== false && !empty($this->_tableNameSettings['tablesNames'])) {
            list($startQuote, $endQuote) = $this->_tableNameSettings['quoteStrings'];
            if (!empty($startQuote)) {
                $lookAhead = $startQuote . '?' .
                    implode($endQuote . '?|' . $startQuote . '?', $this->_tableNameSettings['tablesNames']) .
                    $endQuote . '?';
            } else {
                $lookAhead = implode('|', $this->_tableNameSettings['tablesNames']);
            }

            $wordPattern = $this->buildWordPattern();

            $pattern = '/';
            if (isset($lookAhead)) {
                $pattern .= '(?=(?:' . $lookAhead . '))(' . $wordPattern . ')';
            }

            if (strpos($tableName, '.') !== false) {
                $pattern .= '(\.' . $wordPattern . ')';
            }

            $pattern .= '/';

            return preg_match_all($pattern, $tableName) > 0;
        }

        throw new RuntimeException(sprintf(
            'Cannot safely determine if `%s` is prefixed or not.',
            $tableName
        ));
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
     * @param string|array $field String to check for table name
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
