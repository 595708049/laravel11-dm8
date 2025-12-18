本项目基于 jackfinal/laravel-dm8 构建，并针对 Laravel10，Laravel11 进行了适配与改进。
- 源项目GitHub: https://github.com/Jackfinal/laravel-dm8.git

# DM DB driver for Laravel via DM8

## Laravel-DM8

Laravel-DM8 is an Dm Database Driver package for [Laravel](http://laravel.com/). Laravel-DM8 is an extension of [Illuminate/Database](https://github.com/illuminate/database) that uses [DM8](https://eco.dameng.com/document/dm/zh-cn/faq/faq-php.html#PHP-Startup-Unable-to-load-dynamic-library) extension to communicate with Dm. Thanks to @yajra.

## Documentations

- You will find user-friendly and updated documentation here: [Laravel-DM8 Docs](https://github.com/wangl/laravel-dm8)
- All about dm and php:[The Underground PHP and Dm Manual](https://eco.dameng.com/document/dm/zh-cn/app-dev/php-php.html)

## Laravel Version Compatibility

 Laravel  | Package
:---------|:----------
 10.x.x   | 1.x.x
 11.x.x   | 1.x.x

## Quick Installation

```bash
composer require wangl/laravel-dm8
```

## Service Provider (Optional on Laravel 5.5+)

Once Composer has installed or updated your packages you need to register Laravel-DM8. Open up `config/app.php` and find the providers key and add:

```php
LaravelDm8\Dm8\Dm8ServiceProvider::class,
```

## Configuration (OPTIONAL)

Finally you can optionally publish a configuration file by running the following Artisan command.
If config file is not publish, the package will automatically use what is declared on your `.env` file database configuration.

This will copy the configuration file to `config/database.php`.

> Then, you can set connection data in your `.env` files:

```ini
 'connections' => [
        'mysql' => [
           …………­
        ],

        'dm' => [
            'driver'         => 'dm',
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '5236'),
            'database'       => env('DB_DATABASE', 'laravel'),
            'username'       => env('DB_USERNAME', 'laravel'),
            'password'       => env('DB_PASSWORD', 'laravel'),
            'charset'        => 'UTF8',
            'collation'      => 'utf8_general_ci',
            'prefix'         => '',
            'strict'         => true,
            'engine'         => null,
            'options' => [
                \PDO::ATTR_PERSISTENT => true,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
        ],
    ],
```

Then run your laravel installation...

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[link-author]: https://github.com/595708049
