# Module Manager for Magento 2
This module is a set of console commands that provide functions for easy code generation.

## Installation

```composer require bkozlic/bkozlic/module-manager```

```php bin/magento setup:upgrade```

```php bin/magento setup:di:compile```

## Usage

##### Create a module:
```php bin/magento manager:module:create```

##### Create a configuration file in the etc folder:
```php bin/magento manager:configuration:create```

##### Create a console command:
```php bin/magento manager:command:create```

##### Create a controller:
```php bin/magento manager:controller:create```

##### Create a cron job:
```php bin/magento manager:cron:create```

##### Create a helper:
```php bin/magento manager:helper:create```

##### Create a handle layout xml:
```php bin/magento manager:handle:create```

##### Create a set of model, resource model and collection:
```php bin/magento manager:crud:create```

##### Create an observer:
```php bin/magento manager:observer:create```

##### Create a patch:
```php bin/magento manager:patch:create```

##### Create a plugin:
```php bin/magento manager:plugin:create```

##### Create a route:
```php bin/magento manager:route:create```

##### Create a set of controller, route and handle layout xml
```php bin/magento manager:route-full:create```

## Prerequisites

* PHP >= 7.2

## Developers
* [Berin Kozlic](https://github.com/Beraa995)
