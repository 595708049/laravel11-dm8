<?php

namespace LaravelDm8\Dm8;

use Doctrine\DBAL\Driver\OCI8\Driver as DoctrineDriver;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Database\Grammar;
use Illuminate\Support\Str;
use PDO;
use PDOStatement;
use Throwable;
use LaravelDm8\Dm8\PDO\DmDriver;
use LaravelDm8\Dm8\Query\Grammars\DmGrammar as QueryGrammar;
use LaravelDm8\Dm8\Query\DmBuilder as QueryBuilder;
use LaravelDm8\Dm8\Query\Processors\DmProcessor as Processor;
use LaravelDm8\Dm8\Schema\Grammars\DmGrammar as SchemaGrammar;
use LaravelDm8\Dm8\Schema\DmBuilder as SchemaBuilder;
use LaravelDm8\Dm8\Schema\Sequence;
use LaravelDm8\Dm8\Schema\Trigger;

class Dm8Connection extends Connection
{
    const RECONNECT_ERRORS = 'reconnect_errors';

    /**
     * @var string
     */
    protected $schema;

    /**
     * @var \LaravelDm8\Dm8\Schema\Sequence
     */
    protected $sequence;

    /**
     * @var \LaravelDm8\Dm8\Schema\Trigger
     */
    protected $trigger;

    /**
     * @param  PDO|\Closure  $pdo
     * @param  string  $database
     * @param  string  $tablePrefix
     * @param  array  $config
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        // 确保配置中包含字符集设置
        if (!isset($config['charset'])) {
            $config['charset'] = 'utf8';
        }
        
        parent::__construct($pdo, $database, $tablePrefix, $config);
        $this->sequence = new Sequence($this);
        $this->trigger = new Trigger($this);
    }

    /**
     * Get current schema.
     *
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * Set current schema.
     *
     * @param  string  $schema
     * @return $this
     */
    public function setSchema(string $schema): static
    {
        $this->schema = $schema;
        $sessionVars = [
            'CURRENT_SCHEMA' => $schema,
        ];

        return $this->setSessionVars($sessionVars);
    }

    /**
     * Update dm session variables.
     *
     * @param  array  $sessionVars
     * @return $this
     */
    public function setSessionVars(array $sessionVars): static
    {
        $vars = [];
        foreach ($sessionVars as $option => $value) {
            if (strtoupper($option) == 'CURRENT_SCHEMA' || strtoupper($option) == 'EDITION') {
                $vars[] = "$option  = $value";
            } else {
                $vars[] = "$option  = '$value'";
            }
        }

        if ($vars) {
            $sql = 'ALTER SESSION SET '.implode(' ', $vars);
            $this->statement($sql);
        }

        return $this;
    }

    /**
     * Get sequence class.
     *
     * @return \LaravelDm8\Dm8\Schema\Sequence
     */
    public function getSequence(): Sequence
    {
        return $this->sequence;
    }

    /**
     * Set sequence class.
     *
     * @param  \LaravelDm8\Dm8\Schema\Sequence  $sequence
     * @return \LaravelDm8\Dm8\Schema\Sequence
     */
    public function setSequence(Sequence $sequence): Sequence
    {
        return $this->sequence = $sequence;
    }

    /**
     * Get dm trigger class.
     *
     * @return \LaravelDm8\Dm8\Schema\Trigger
     */
    public function getTrigger(): Trigger
    {
        return $this->trigger;
    }

    /**
     * Set dm trigger class.
     *
     * @param  \LaravelDm8\Dm8\Schema\Trigger  $trigger
     * @return \LaravelDm8\Dm8\Schema\Trigger
     */
    public function setTrigger(Trigger $trigger): Trigger
    {
        return $this->trigger = $trigger;
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \LaravelDm8\Dm8\Schema\DmBuilder
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }

    /**
     * Get a new query builder instance.
     *
     * @return \LaravelDm8\Dm8\Query\DmBuilder
     */
    public function query(): QueryBuilder
    {
        // 确保使用正确的 DmGrammar
        $grammar = $this->getQueryGrammar();
        return new QueryBuilder(
            $this, $grammar, $this->getPostProcessor()
        );
    }

    /**
     * Set dm session date format.
     *
     * @param  string  $format
     * @return $this
     */
    public function setDateFormat(string $format = 'YYYY-MM-DD HH24:MI:SS'): static
    {
        $sessionVars = [
            'NLS_DATE_FORMAT'      => $format,
            'NLS_TIMESTAMP_FORMAT' => $format,
        ];

        return $this->setSessionVars($sessionVars);
    }

    /**
     * Get doctrine driver.
     *
     * @return \Doctrine\DBAL\Driver\OCI8\Driver
     */
    protected function getDoctrineDriver(): DoctrineDriver
    {
        return new DoctrineDriver();
    }

    /**
     * Execute a PL/SQL Function and return its value.
     * Usage: DB::executeFunction('function_name', ['binding_1' => 'hi', 'binding_n' =>
     * 'bye'], PDO::PARAM_LOB).
     *
     * @param  string  $functionName
     * @param  array  $bindings  (kvp array)
     * @param  int  $returnType  (PDO::PARAM_*)
     * @param  int|null  $length
     * @return mixed $returnType
     */
    public function executeFunction(string $functionName, array $bindings = [], int $returnType = PDO::PARAM_STR, ?int $length = null): mixed
    {
        $stmt = $this->createStatementFromFunction($functionName, $bindings);

        $stmt = $this->addBindingsToStatement($stmt, $bindings);

        $stmt->bindParam(':result', $result, $returnType, $length);
        $stmt->execute();

        return $result;
    }

    /**
     * Execute a PL/SQL Procedure and return its results.
     *
     * Usage: DB::executeProcedure($procedureName, $bindings).
     * $bindings looks like:
     *         $bindings = [
     *                  'p_userid'  => $id
     *         ];
     *
     * @param  string  $procedureName
     * @param  array  $bindings
     * @return bool
     */
    public function executeProcedure(string $procedureName, array $bindings = []): bool
    {
        $stmt = $this->createStatementFromProcedure($procedureName, $bindings);

        $stmt = $this->addBindingsToStatement($stmt, $bindings);

        return $stmt->execute();
    }

    /**
     * Execute a PL/SQL Procedure and return its cursor result.
     * Usage: DB::executeProcedureWithCursor($procedureName, $bindings).
     *
     * https://docs.oracle.com/cd/E17781_01/appdev.112/e18555/ch_six_ref_cur.htm#TDPPH218
     *
     * @param  string  $procedureName
     * @param  array  $bindings
     * @param  string  $cursorName
     * @return array
     */
    public function executeProcedureWithCursor(string $procedureName, array $bindings = [], string $cursorName = ':cursor'): array
    {
        $stmt = $this->createStatementFromProcedure($procedureName, $bindings, $cursorName);

        $stmt = $this->addBindingsToStatement($stmt, $bindings);

        $cursor = null;
        $stmt->bindParam($cursorName, $cursor, PDO::PARAM_STMT);
        $stmt->execute();

        // Directly use the cursor as PDOStatement
        $results = $cursor->fetchAll(PDO::FETCH_OBJ);
        $cursor->closeCursor();

        return $results;
    }

    /**
     * Creates sql command to run a procedure with bindings.
     *
     * @param  string  $procedureName
     * @param  array  $bindings
     * @param  string|bool  $cursor
     * @return string
     */
    public function createSqlFromProcedure(string $procedureName, array $bindings, string|bool $cursor = false): string
    {
        $paramsString = implode(',', array_map(function (string $param) {
            return ':'.$param;
        }, array_keys($bindings)));

        $prefix = count($bindings) ? ',' : '';
        $cursor = $cursor ? $prefix.$cursor : null;

        return sprintf('begin %s(%s%s); end;', $procedureName, $paramsString, $cursor);
    }

    /**
     * Creates statement from procedure.
     *
     * @param  string  $procedureName
     * @param  array  $bindings
     * @param  string|bool  $cursorName
     * @return PDOStatement
     */
    public function createStatementFromProcedure(string $procedureName, array $bindings, string|bool $cursorName = false): PDOStatement
    {
        $sql = $this->createSqlFromProcedure($procedureName, $bindings, $cursorName);

        return $this->getPdo()->prepare($sql);
    }

    /**
     * Create statement from function.
     *
     * @param  string  $functionName
     * @param  array  $bindings
     * @return PDOStatement
     */
    public function createStatementFromFunction(string $functionName, array $bindings): PDOStatement
    {
        $bindingsStr = $bindings ? ':'.implode(', :', array_keys($bindings)) : '';

        $sql = sprintf('begin :result := %s(%s); end;', $functionName, $bindingsStr);

        return $this->getPdo()->prepare($sql);
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Illuminate\Database\Grammar|\LaravelDm8\Dm8\Query\Grammars\DmGrammar
     */
    protected function getDefaultQueryGrammar(): Grammar
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    /**
     * Set the table prefix and return the grammar.
     *
     * @param  \Illuminate\Database\Grammar|\LaravelDm8\Dm8\Query\Grammars\DmGrammar|\LaravelDm8\Dm8\Schema\Grammars\DmGrammar  $grammar
     * @return \Illuminate\Database\Grammar
     */
    public function withTablePrefix(Grammar $grammar): Grammar
    {
        return $this->withSchemaPrefix(parent::withTablePrefix($grammar));
    }

    /**
     * Set the schema prefix and return the grammar.
     *
     * @param  \Illuminate\Database\Grammar|\LaravelDm8\Dm8\Query\Grammars\DmGrammar|\LaravelDm8\Dm8\Schema\Grammars\DmGrammar  $grammar
     * @return \Illuminate\Database\Grammar
     */
    public function withSchemaPrefix(Grammar $grammar): Grammar
    {
        $grammar->setSchemaPrefix($this->getConfigSchemaPrefix());

        return $grammar;
    }

    /**
     * Get config schema prefix.
     *
     * @return string
     */
    protected function getConfigSchemaPrefix(): string
    {
        return $this->config['prefix_schema'] ?? '';
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Grammar|\LaravelDm8\Dm8\Schema\Grammars\DmGrammar
     */
    protected function getDefaultSchemaGrammar(): Grammar
    {
        return $this->withTablePrefix(new SchemaGrammar());
    }

    /**
     * Get the default post processor instance.
     *
     * @return \LaravelDm8\Dm8\Query\Processors\DmProcessor
     */
    protected function getDefaultPostProcessor(): Processor
    {
        return new Processor();
    }

    /**
     * Add bindings to statement.
     *
     * @param  PDOStatement  $stmt
     * @param  array  $bindings
     * @return PDOStatement
     */
    public function addBindingsToStatement(PDOStatement $stmt, array $bindings): PDOStatement
    {
        foreach ($bindings as $key => &$binding) {
            $value = &$binding;
            $type = PDO::PARAM_STR;
            $length = -1;

            if (is_array($binding)) {
                $value = &$binding['value'];
                $type = $binding['type'] ?? PDO::PARAM_STR;
                $length = $binding['length'] ?? -1;
            }

            $stmt->bindParam(':'.$key, $value, $type, $length);
        }

        return $stmt;
    }

    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    protected function causedByLostConnection(Throwable $e): bool
    {
        if (parent::causedByLostConnection($e)) {
            return true;
        }

        $lostConnectionErrors = [
            'DM-',          //达梦数据库错误前缀
            '10054',        //连接被远程主机强制关闭
            '10060',        //连接超时
            '10061',        //连接被拒绝
            '10065',        //无法访问目标主机
            'HY000',        //一般错误
        ];

        $additionalErrors = $this->config['options'][static::RECONNECT_ERRORS] ?? null;

        if (is_array($additionalErrors)) {
            $lostConnectionErrors = array_merge($lostConnectionErrors, $additionalErrors);
        }

        return Str::contains($e->getMessage(), $lostConnectionErrors);
    }

    /**
     * Set dm session to case insensitive search & sort.
     *
     * @return $this
     */
    public function useCaseInsensitiveSession(): static
    {
        return $this->setSessionVars(['NLS_COMP' => 'LINGUISTIC', 'NLS_SORT' => 'BINARY_CI']);
    }

    /**
     * Set dm session to case sensitive search & sort.
     *
     * @return $this
     */
    public function useCaseSensitiveSession(): static
    {
        return $this->setSessionVars(['NLS_COMP' => 'BINARY', 'NLS_SORT' => 'BINARY']);
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param  mixed  $statement
     * @param  array  $bindings
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }
            
            $statement->bindValue(is_string($key) ? $key : $key + 1, $value, $type);
        }
    }

    /**
     * 执行查询并返回第一个结果
     * 覆盖父类方法以避免嵌套查询
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true)
    {
        // 如果是简单的 where 查询，使用达梦兼容的语法
        if (is_string($query) && strpos($query, 'select * from (select') !== false) {
            // 简化嵌套查询
            $query = preg_replace('/select \* from \((select .*?)\) where rownum = 1/is', '$1 and rownum = 1', $query);
        }
        
        $results = $this->select($query, $bindings, $useReadPdo);
        
        return count($results) > 0 ? reset($results) : null;
    }
}
