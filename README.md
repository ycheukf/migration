migration
=========

Introduction
------------

YcheukfMigration is a db migration module for zend framework 2. It just like YII.
Project originally based on <a href="https://github.com/vgarvardt/ZfSimpleMigrations">ZfSimpleMigrations</a>, and support multiple db migration.
Only run at command line.

Features / Goals
----------------

* db migration like YII, ROR. (finished)
* mult db migration support. (finished)

Requirements
------------

* [PHP5](https://php.net/)
* [Zend Framework 2](https://github.com/zendframework/zf2) - Not needed to generate your models
* [ycheukf/debug](https://packagist.org/packages/ycheukf/debug) - A debug module for zf2


Installation
------------

**Install via git**

Clone this repo
`git clone https://github.com/ycheukf/migration.git`

**Install via Composer**

Add this to your composer.json under "require":
`"ycheukf/migration": "dev-master"`

Run command:
``php composer.phar update``


Usage
-----

1:  add module 'YcheukfMigration' to your application.config.php
```php
return array(
    'modules' => array(
        'YcheukfMigration',
        'Application',
    ),
);
```

2: get the helpinfo by running "php public/index.php"


Example
-----

1: generate a new migration-file in path/migrations/default
```php
	php public/index.php migration generate
```

2: apply a migration (upgrade or downgrade) .
```shell
	php public/index.php migration up db;
//	or
	php public/index.php migration down db;
```
 "db" is the key of the db-config array, usually written at path/config/autoload/global.php
```php

	return array(
		'db' => array(//that is it 
			'driver'         => 'Pdo',
			'dsn'            => 'mysql:dbname=myzf2;host=localhost',
			'username' => 'root',
			'password' => 'root',
			'driver_options' => array(
				\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
			),
		),
//		...
	);
```

3: apply migration for all the db-array which is returned by an event "setDbConfigsFromEvent"
```php
	php public/index.php migration up --dbsfromevent
//	or
	php public/index.php migration down --dbsfromevent
```

4: apply migration for a key "db" of the db-array which is returned by an event "setDbConfigsFromEvent"
```php
	php public/index.php migration up db --dbsfromevent
//	or
	php public/index.php migration down db --dbsfromevent
```

