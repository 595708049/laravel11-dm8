<?php

namespace LaravelDm8\Dm8\Schema;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;

class DmBuilder extends Builder
{
    /**
     * @var \LaravelDm8\Dm8\Schema\DmAutoIncrementHelper
     */
    public $helper;

    /**
     * @var \LaravelDm8\Dm8\Schema\Comment
     */
    public $comment;

    /**
     * @param  Connection  $connection
     */
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
        $this->helper = new DmAutoIncrementHelper($connection);
        $this->comment = new Comment($connection);
    }

    /**
     * Create a new table on the schema.
     *
     * @param  string  $table
     * @param  Closure  $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    public function create($table, Closure $callback)
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->create();

        $callback($blueprint);

        $this->build($blueprint);

        $this->comment->setComments($blueprint);

        $this->helper->createAutoIncrementObjects($blueprint, $table);
    }

    /**
     * Create a new command set with a Closure.
     *
     * @param  string  $table
     * @param  Closure  $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    protected function createBlueprint($table, Closure $callback = null)
    {
        $blueprint = new DmBlueprint($table, $callback);
        $blueprint->setTablePrefix($this->connection->getTablePrefix());

        return $blueprint;
    }

    /**
     * Changes an existing table on the schema.
     *
     * @param  string  $table
     * @param  Closure  $callback
     * @return \Illuminate\Database\Schema\Blueprint
     */
    public function table($table, Closure $callback)
    {
        $blueprint = $this->createBlueprint($table);

        $callback($blueprint);

        foreach ($blueprint->getCommands() as $command) {
            if ($command->get('name') == 'drop') {
                $this->helper->dropAutoIncrementObjects($table);
            }
        }

        $this->build($blueprint);

        $this->comment->setComments($blueprint);
    }

    /**
     * Drop a table from the schema.
     *
     * @param  string  $table
     * @return \Illuminate\Database\Schema\Blueprint
     */
    public function drop($table)
    {
        $this->helper->dropAutoIncrementObjects($table);
        parent::drop($table);
    }

    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function dropAllTables()
    {
        $this->connection->statement($this->grammar->compileDropAllTables());
    }

    /**
     * Indicate that the table should be dropped if it exists.
     *
     * @param  string  $table
     * @return \Illuminate\Support\Fluent
     */
    public function dropIfExists($table)
    {
        $this->helper->dropAutoIncrementObjects($table);
        parent::dropIfExists($table);
    }

    /**
     * Determine if the given table exists.
     *
     * @param  string  $table
     * @return bool
     */
    public function hasTable($table)
    {
        /** @var \Yajra\Oci8\Schema\Grammars\OracleGrammar $grammar */
        $grammar = $this->grammar;
        $sql = $grammar->compileTableExists();

        $database = $this->connection->getConfig('username');
        if ($this->connection->getConfig('prefix_schema')) {
            $database = $this->connection->getConfig('prefix_schema');
        }
        $table = $this->connection->getTablePrefix().$table;

        return count($this->connection->select($sql, [$database, $table])) > 0;
    }

    /**
     * Determine if the given table has a given column.
     *
     * @param  string  $table
     * @param  string  $column
     * @return bool
     */
    public function hasColumn($table, $column)
    {
        // 1) 先走原有逻辑（不改变现有行为）
        $columns = array_map('strtolower', $this->getColumnListing($table));
        if (in_array(strtolower($column), $columns, true)) {
            return true;
        }

        // 2) 原逻辑失败时，达梦兜底（仅附加，不破坏旧路径）
        $schema = $this->connection->getConfig('prefix_schema') ?: $this->connection->getConfig('username');
        $tableName = $table;

        if (strpos($table, '.') !== false) {
            [$schemaPart, $tablePart] = explode('.', $table, 2);
            $schema = $schemaPart;
            $tableName = $tablePart;
        }

        $tableWithPrefix = $this->connection->getTablePrefix().$tableName;

        $sql = 'SELECT 1
                FROM ALL_TAB_COLUMNS
                WHERE UPPER(OWNER) = UPPER(?)
                AND UPPER(TABLE_NAME) = UPPER(?)
                AND UPPER(COLUMN_NAME) = UPPER(?)
                FETCH FIRST 1 ROWS ONLY';

        // 2.1 带前缀表名
        $rows = $this->connection->select($sql, [$schema, $tableWithPrefix, $column]);
        if (!empty($rows)) {
            return true;
        }

        // 2.2 无前缀表名
        $rows = $this->connection->select($sql, [$schema, $tableName, $column]);
        if (!empty($rows)) {
            return true;
        }

        // 2.3 跨 schema 兜底（仅按表名+列名）
        $sql2 = 'SELECT 1
                FROM ALL_TAB_COLUMNS
                WHERE UPPER(TABLE_NAME) = UPPER(?)
                AND UPPER(COLUMN_NAME) = UPPER(?)
                FETCH FIRST 1 ROWS ONLY';

        $rows = $this->connection->select($sql2, [$tableWithPrefix, $column]);
        if (!empty($rows)) {
            return true;
        }

        $rows = $this->connection->select($sql2, [$tableName, $column]);
        return !empty($rows);
    }



    /**
     * Get the column listing for a given table.
     *
     * @param  string  $table
     * @return array
     */
    public function getColumnListing($table)
    {
        [$schema, $tableName] = $this->resolveSchemaAndTable($table);

        $rows = $this->queryColumns($tableName, $schema);

        // 去掉前缀重试，避免前缀与真实对象名不一致导致查不到
        if (empty($rows)) {
            $prefix = strtoupper((string) $this->connection->getTablePrefix());
            if ($prefix !== '' && stripos($tableName, $prefix) === 0) {
                $rawTableName = substr($tableName, strlen($prefix));
                $rows = $this->queryColumns($rawTableName, $schema);

                if (empty($rows)) {
                    $rows = $this->queryColumns($rawTableName, null);
                }
            }
        }

        // 兜底：不带 owner 再查一次
        if (empty($rows)) {
            $rows = $this->queryColumns($tableName, null);
        }

        return $this->extractColumnNames($rows);
    }

    /**
     * @param  string  $table
     * @return array
     */
    protected function resolveSchemaAndTable($table)
    {
        $schema = $this->connection->getConfig('prefix_schema') ?: $this->connection->getConfig('username');

        if (strpos($table, '.') !== false) {
            [$schemaPart, $tablePart] = explode('.', $table, 2);
            $schema = $schemaPart;
            $table = $tablePart;
        }

        $tablePrefix = (string) $this->connection->getTablePrefix();

        // 兼容把 schema 写在 prefix 里的配置（例如: XGFZ.XGFZ_）
        if (strpos($tablePrefix, '.') !== false) {
            [$prefixSchema, $prefixOnly] = explode('.', $tablePrefix, 2);

            if (empty($this->connection->getConfig('prefix_schema')) && ! empty($prefixSchema)) {
                $schema = $prefixSchema;
            }

            $tablePrefix = $prefixOnly;
        }

        if ($tablePrefix !== '' && stripos($table, $tablePrefix) !== 0) {
            $table = $tablePrefix.$table;
        }

        return [strtoupper((string) $schema), strtoupper((string) $table)];
    }

    /**
     * @param  string  $tableName
     * @param  string|null  $owner
     * @return array
     */
    protected function queryColumns($tableName, $owner = null)
    {
        if (! empty($owner)) {
            $sql = 'SELECT COLUMN_NAME FROM ALL_TAB_COLUMNS WHERE UPPER(OWNER) = UPPER(?) AND UPPER(TABLE_NAME) = UPPER(?) ORDER BY COLUMN_ID';

            return $this->connection->select($sql, [$owner, $tableName]);
        }

        $sql = 'SELECT COLUMN_NAME FROM ALL_TAB_COLUMNS WHERE UPPER(TABLE_NAME) = UPPER(?) ORDER BY COLUMN_ID';

        return $this->connection->select($sql, [$tableName]);
    }

    /**
     * @param  array  $rows
     * @return array
     */
    protected function extractColumnNames(array $rows)
    {
        $columns = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $columns[] = $row['COLUMN_NAME'] ?? $row['column_name'] ?? null;
            } else {
                $columns[] = $row->COLUMN_NAME ?? $row->column_name ?? null;
            }
        }

        return array_values(array_filter($columns, function ($value) {
            return $value !== null && $value !== '';
        }));
    }
}

