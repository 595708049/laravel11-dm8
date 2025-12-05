<?php

namespace LaravelDm8\Dm8\Query\Processors;

use DateTime;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use PDO;

class DmProcessor extends Processor
{
    /**
     * Process the results of a "select" query.
     *
     * @param  Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processSelect(Builder $query, $results)
    {
        // 将结果集中的列名转换为小写，确保 Eloquent 模型能正确访问属性
        $processedResults = [];
        
        foreach ($results as $result) {
            $processedResult = [];
            
            foreach ($result as $key => $value) {
                // 处理值的 UTF-8 编码
                if (is_string($value)) {
                    // 确保字符串是有效的 UTF-8 编码
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        // 使用更可靠的方式转换为 UTF-8，避免无法检测编码的警告
                        // 先尝试检测编码，然后转换
                        $encoding = mb_detect_encoding($value, ['UTF-8', 'GBK', 'GB2312', 'ISO-8859-1'], true);
                        if ($encoding) {
                            $value = mb_convert_encoding($value, 'UTF-8', $encoding);
                        } else {
                            // 如果无法检测编码，使用 utf8_encode 作为备选方案
                            $value = utf8_encode($value);
                        }
                    }
                }
                
                // 将键名转换为小写
                $processedResult[strtolower($key)] = $value;
            }
            
            $processedResults[] = $processedResult;
        }
        
        return $processedResults;
    }

    /**
     * Process an "insert get ID" query.
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $connection = $query->getConnection();

        $connection->recordsHaveBeenModified();
        $start = microtime(true);

        $id = 0;
        $parameter = 1;
        $statement = $this->prepareStatement($query, $sql);
        $values = $this->incrementBySequence($values, $sequence);
        $parameter = $this->bindValues($values, $statement, $parameter);
        $statement->bindParam($parameter, $id, PDO::PARAM_INT, -1);
        $statement->execute();

        $connection->logQuery($sql, $values, $start);

        return (int) $id;
    }

    /**
     * Get prepared statement.
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @return \PDOStatement|\Yajra\Pdo\Oci8
     */
    private function prepareStatement(Builder $query, $sql)
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $query->getConnection();
        $pdo = $connection->getPdo();

        return $pdo->prepare($sql);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array  $values
     * @param  string  $sequence
     * @return array
     */
    protected function incrementBySequence(array $values, $sequence)
    {
        $builder = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)[3]['object'];
        $builderArgs = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5)[2]['args'];

        if (! isset($builderArgs[1][0][$sequence])) {
            if ($builder instanceof EloquentBuilder) {
                /** @var \Yajra\Oci8\Eloquent\OracleEloquent $model */
                $model = $builder->getModel();
                /** @var \Yajra\Oci8\Oci8Connection $connection */
                $connection = $model->getConnection();
                if ($model->sequence && $model->incrementing) {
                    $values[] = (int) $connection->getSequence()->nextValue($model->sequence);
                }
            }
        }

        return $values;
    }

    /**
     * Bind values to PDO statement.
     *
     * @param  array  $values
     * @param  \PDOStatement  $statement
     * @param  int  $parameter
     * @return int
     */
    private function bindValues(&$values, $statement, $parameter)
    {
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            if (is_object($values[$i])) {
                if ($values[$i] instanceof DateTime) {
                    $values[$i] = $values[$i]->format('Y-m-d H:i:s');
                } else {
                    $values[$i] = (string) $values[$i];
                }
            }
            $type = $this->getPdoType($values[$i]);
            $statement->bindParam($parameter, $values[$i], $type);
            $parameter++;
        }

        return $parameter;
    }

    /**
     * Get PDO Type depending on value.
     *
     * @param  mixed  $value
     * @return int
     */
    private function getPdoType($value)
    {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }

        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }

        if (is_null($value)) {
            return PDO::PARAM_NULL;
        }

        return PDO::PARAM_STR;
    }

    /**
     * Save Query with Blob returning primary key value.
     *
     * @param  Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  array  $binaries
     * @return int
     */
    public function saveLob(Builder $query, $sql, array $values, array $binaries)
    {
        $connection = $query->getConnection();

        $connection->recordsHaveBeenModified();
        $start = microtime(true);

        $id = 0;
        $parameter = 1;
        $statement = $this->prepareStatement($query, $sql);

        $parameter = $this->bindValues($values, $statement, $parameter);

        $countBinary = count($binaries);
        for ($i = 0; $i < $countBinary; $i++) {
            $statement->bindParam($parameter, $binaries[$i], PDO::PARAM_LOB, -1);
            $parameter++;
        }

        // bind output param for the returning clause.
        $statement->bindParam($parameter, $id, PDO::PARAM_INT, -1);

        if (! $statement->execute()) {
            return false;
        }

        $connection->logQuery($sql, $values, $start);

        return (int) $id;
    }

    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        $mapping = function ($r) {
            $r = (object) $r;
            
            // 方法一：指定源编码（根据你的 DM8 数据库编码设置）
            $columnName = $r->column_name;
            
            // 尝试不同的编码
            $encodings = ['GBK', 'GB2312', 'UTF-8', 'ASCII', 'ISO-8859-1'];
            
            foreach ($encodings as $encoding) {
                $converted = @mb_convert_encoding($columnName, 'UTF-8', $encoding);
                if ($converted && mb_check_encoding($converted, 'UTF-8')) {
                    return $converted;
                }
            }
            
            // 如果都失败，使用原始值
            return $columnName;
        };

        return array_map($mapping, $results);
    }
}
