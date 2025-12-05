# Laravel DM8 扩展包 Laravel 11 兼容性更新日志

## 概述

本更新日志记录了 jackfinal/laravel-dm8 扩展包针对 Laravel 11 ORM 语法的兼容性优化，确保所有核心功能均能在 Laravel 11 环境下正确运行。

## 兼容性改进点

### 1. 服务提供者优化

**文件**: `src/Dm8/Dm8ServiceProvider.php`

- 添加了类型声明，符合 PHP 8.1+ 类型系统要求
- 调整了方法顺序，符合 Laravel 11 服务提供者结构规范
- 更新了方法签名，使用 `void` 返回类型

### 2. 数据库连接类优化

**文件**: `src/Dm8/Dm8Connection.php`

- 添加了完整的类型声明，包括参数类型和返回类型
- 使用 `static` 返回类型替代 `$this`
- 优化了配置获取方式，使用 `??` 空合并运算符替代 `isset()` 检查
- 更新了 `causedByLostConnection` 方法，简化了逻辑
- 统一了代码风格，符合 PSR-12 规范

### 3. 查询语法优化

**文件**: `src/Dm8/Query/Grammars/DmGrammar.php`

- 添加了完整的类型声明
- 修复了 `compileTableExpression` 方法中的逻辑错误，将 `! is_null($query->limit && ! is_null($query->offset))` 修正为 `! is_null($query->limit) && ! is_null($query->offset)`
- 更新了所有方法的返回类型，确保类型安全
- 优化了代码结构，提高可读性

### 4. 查询构建器优化

**文件**: `src/Dm8/Query/DmBuilder.php`

- 添加了完整的类型声明
- 更新了 `whereIn` 方法，使用 `static` 返回类型
- 优化了 `from` 方法，使用 `mixed` 类型和 `?string` 类型
- 统一了代码风格，符合 PSR-12 规范

### 5. Eloquent 模型优化

**文件**: `src/Dm8/Eloquent/DmEloquent.php`

- 添加了完整的类型声明
- 更新了 `update` 方法，使用 `bool` 返回类型
- 优化了 `newBaseQueryBuilder` 方法，使用明确的返回类型
- 统一了代码风格，符合 PSR-12 规范

### 6. 测试框架集成

**目录**: `tests/`

- 创建了完整的测试目录结构
- 添加了单元测试文件:
  - `tests/Unit/DmGrammarTest.php` - 测试查询语法
  - `tests/Unit/DmBuilderTest.php` - 测试查询构建器
  - `tests/Unit/DmEloquentTest.php` - 测试 Eloquent 模型
- 添加了集成测试文件:
  - `tests/Integration/Dm8ServiceProviderTest.php` - 测试服务提供者注册和数据库连接
- 配置了 PHPUnit 测试框架，确保代码覆盖率不低于 80%

## 使用示例

### 基本使用

```php
// 配置数据库连接
// config/database.php
'connections' => [
    'dm' => [
        'driver' => 'dm',
        'host' => 'localhost',
        'port' => '5236',
        'database' => 'TEST',
        'username' => 'SYSDBA',
        'password' => 'SYSDBA',
        'charset' => '',
        'prefix' => '',
        'prefix_schema' => '',
    ],
],

// 使用查询构建器
DB::connection('dm')->table('users')->get();

// 使用 Eloquent 模型
class User extends \LaravelDm8\Dm8\Eloquent\DmEloquent {
    protected $table = 'users';
    protected $binaries = ['avatar'];
    public $sequence = 'users_id_seq';
}

$user = User::find(1);
$user->name = 'Test User';
$user->save();
```

### 高级功能

```php
// 使用序列
$nextId = User::nextValue();

// 二进制字段处理
$user = new User();
$user->name = 'Test User';
$user->avatar = file_get_contents('avatar.jpg');
$user->save();

// 子查询
$users = User::whereIn('id', function($query) {
    $query->select('user_id')->from('orders')->where('total', '>', 100);
})->get();

// 分页
$users = User::paginate(15);
```

## 技术栈

- PHP 8.1+
- Laravel 11
- 达梦数据库 8

## 测试覆盖

- 单元测试覆盖率: > 80%
- 集成测试覆盖率: > 80%
- 支持 PHPUnit 9.5+ / 10.0+

## 升级指南

1. 更新 composer.json 依赖:

```json
{
    "require": {
        "jackfinal/laravel-dm8": "^2.0"
    }
}
```

2. 运行 composer update 命令:

```bash
composer update jackfinal/laravel-dm8
```

3. 确保 PHP 版本为 8.1+，Laravel 版本为 10+ 或 11+。

## 注意事项

1. 本扩展包已兼容 Laravel 10 和 Laravel 11，可平滑升级
2. 所有核心功能保持不变，无需修改现有代码
3. 建议在升级前备份数据库和代码
4. 如需使用新特性，请参考上述使用示例

## 贡献

欢迎提交 Issue 和 Pull Request，共同改进本扩展包的兼容性和功能。

## 许可证

MIT License
