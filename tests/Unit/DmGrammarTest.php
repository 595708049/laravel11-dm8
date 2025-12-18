<?php

namespace LaravelDm8\Tests\Unit;

use Illuminate\Database\Query\Builder;
use LaravelDm8\Dm8\Query\Grammars\DmGrammar;
use PHPUnit\Framework\TestCase;

class DmGrammarTest extends TestCase
{
    /**
     * @var DmGrammar
     */
    protected $grammar;

    /**
     * @var Builder
     */
    protected $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grammar = new DmGrammar();
        $this->query = $this->createMock(Builder::class);
    }

    /**
     * Test compileSelect method.
     */
    public function testCompileSelect()
    {
        $this->query->from = 'users';
        $this->query->columns = ['id', 'name', 'email'];
        $this->query->joins = [];
        $this->query->wheres = [];
        $this->query->groups = [];
        $this->query->havings = [];
        $this->query->orders = [];
        $this->query->limit = null;
        $this->query->offset = null;
        $this->query->unions = [];
        $this->query->aggregate = null;

        $sql = $this->grammar->compileSelect($this->query);
        $this->assertEquals('select id, name, email from users', $sql);
    }

    /**
     * Test wrapTable method.
     */
    public function testWrapTable()
    {
        $this->assertEquals('users', $this->grammar->wrapTable('users'));
        $this->assertEquals('users u', $this->grammar->wrapTable('users as u'));
    }

    /**
     * Test wrap method.
     */
    public function testWrap()
    {
        $this->assertEquals('id', $this->grammar->wrap('id'));
        $this->assertEquals('123', $this->grammar->wrap(123));
        $this->assertEquals('123.45', $this->grammar->wrap(123.45));
    }

    /**
     * Test compileInsert method.
     */
    public function testCompileInsert()
    {
        $this->query->from = 'users';
        $sql = $this->grammar->compileInsert($this->query, ['name' => 'test', 'email' => 'test@example.com']);
        $this->assertEquals('insert into users (name, email) values (?, ?)', $sql);
    }

    /**
     * Test compileUpdate method.
     */
    public function testCompileUpdate()
    {
        $this->query->from = 'users';
        $this->query->wheres = [
            [
                'type' => 'basic',
                'column' => 'id',
                'operator' => '=',
                'value' => 1,
            ],
        ];
        $sql = $this->grammar->compileUpdate($this->query, ['name' => 'updated']);
        $this->assertEquals('update users set name = ? where id = ?', $sql);
    }
}
