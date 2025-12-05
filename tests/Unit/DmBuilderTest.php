<?php

namespace LaravelDm8\Tests\Unit;

use Illuminate\Database\Connection;
use LaravelDm8\Dm8\Query\DmBuilder;
use LaravelDm8\Dm8\Query\Grammars\DmGrammar;
use LaravelDm8\Dm8\Query\Processors\DmProcessor;
use PHPUnit\Framework\TestCase;

class DmBuilderTest extends TestCase
{
    /**
     * @var DmBuilder
     */
    protected $builder;

    /**
     * @var Connection
     */
    protected $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createMock(Connection::class);
        $grammar = new DmGrammar();
        $processor = new DmProcessor();
        $this->builder = new DmBuilder($this->connection, $grammar, $processor);
    }

    /**
     * Test from method.
     */
    public function testFrom()
    {
        $builder = $this->builder->from('users');
        $this->assertEquals('users', $builder->from);
        $this->assertSame($this->builder, $builder);

        $builder = $this->builder->from('users', 'u');
        $this->assertEquals('users u', $builder->from);
    }

    /**
     * Test whereIn method.
     */
    public function testWhereIn()
    {
        $this->builder->from('users');
        $this->builder->whereIn('id', [1, 2, 3]);
        $this->assertCount(1, $this->builder->wheres);
        $this->assertEquals('In', $this->builder->wheres[0]['type']);
    }

    /**
     * Test whereNotIn method.
     */
    public function testWhereNotIn()
    {
        $this->builder->from('users');
        $this->builder->whereNotIn('id', [1, 2, 3]);
        $this->assertCount(1, $this->builder->wheres);
        $this->assertEquals('NotIn', $this->builder->wheres[0]['type']);
    }
}
