<?php

namespace LaravelDm8\Tests\Unit;

use LaravelDm8\Dm8\Eloquent\DmEloquent;
use PHPUnit\Framework\TestCase;

class DmEloquentTest extends TestCase
{
    /**
     * Test getSequenceName method.
     */
    public function testGetSequenceName()
    {
        $model = new class extends DmEloquent {
            protected $table = 'users';
            protected $primaryKey = 'id';
        };

        $this->assertEquals('users_id_seq', $model->getSequenceName());
    }

    /**
     * Test setSequenceName method.
     */
    public function testSetSequenceName()
    {
        $model = new class extends DmEloquent {
            protected $table = 'users';
        };

        $model->setSequenceName('custom_seq');
        $this->assertEquals('custom_seq', $model->getSequenceName());
    }

    /**
     * Test checkBinary method.
     */
    public function testCheckBinary()
    {
        $model = new class extends DmEloquent {
            protected $binaries = ['avatar', 'document'];
        };

        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('checkBinary');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($model, ['avatar' => 'binary data']));
        $this->assertFalse($method->invoke($model, ['name' => 'test']));
    }

    /**
     * Test extractBinaries method.
     */
    public function testExtractBinaries()
    {
        $model = new class extends DmEloquent {
            protected $binaries = ['avatar', 'document'];
        };

        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('extractBinaries');
        $method->setAccessible(true);

        $attributes = ['name' => 'test', 'avatar' => 'binary data'];
        $result = $method->invoke($model, $attributes);

        $this->assertEquals(['avatar' => 'binary data'], $result);
        $this->assertEquals(['name' => 'test'], $attributes);
    }
}
