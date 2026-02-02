# A Laravel package that adds type-safe settings attributes to Eloquent models with automatic casting and validation 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/androlax2/laravel-model-typed-settings.svg?style=flat-square)](https://packagist.org/packages/androlax2/laravel-model-typed-settings)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/androlax2/laravel-model-typed-settings/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/androlax2/laravel-model-typed-settings/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/androlax2/laravel-model-typed-settings/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/androlax2/laravel-model-typed-settings/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/androlax2/laravel-model-typed-settings.svg?style=flat-square)](https://packagist.org/packages/androlax2/laravel-model-typed-settings)

## Installation

You can install the package via composer:

```bash
composer require androlax2/laravel-model-typed-settings
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-model-typed-settings-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-model-typed-settings-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-model-typed-settings-views"
```

## Usage

```php
$laravelModelTypedSettings = new Androlax2\LaravelModelTypedSettings();
echo $laravelModelTypedSettings->echoPhrase('Hello, Androlax2!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Androlax2](https://github.com/Androlax2)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
