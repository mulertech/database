# Database

___

The MulerTech database package is a simple database abstraction layer and object relational maping.

___

## Installation

###### _Two methods to install Database package with composer :_

1.
Add to your "**composer.json**" file into require section :

```
"mulertech/database": "^1.0"
```

and run the command :

```
php composer.phar update
```

2.
Run the command :

```
php composer.phar require mulertech/database "^1.0"
```

___

## Usage

<br>

###### Initialize PhpDatabaseManagerInterface :

```
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;

$phpDatabaseManager = new PhpDatabaseManager(new PdoConnector(new Driver()), $parameters);
```

```
or with container :
//definitions file :
return [
    new \MulerTech\Container\Definition(
        \MulerTech\Database\PhpInterface\ConnectorInterface::class,
        \MulerTech\Database\PhpInterface\PdoConnector::class
    ),
    new \MulerTech\Container\Definition(
        \MulerTech\Database\PhpInterface\DriverInterface::class,
        \MulerTech\Database\PhpInterface\PdoMysql\Driver::class
    ),
    new \MulerTech\Container\Definition(
        \MulerTech\Database\PhpInterface\PhpDatabaseInterface::class,
        \MulerTech\Database\PhpInterface\PhpDatabaseManager::class,
        ['parameters' => '%database.phpdatabasemanager.parameters%']
    ),
];
```

<br>

###### _To be continued :_

```

```
