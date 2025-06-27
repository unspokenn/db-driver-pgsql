## About
tenancy/tenancy pgsql database connection driver. The package has been created by modifying the official MySQL driver and works on Laravel 11+ (the Doctrine/DBAL dependency has been removed).

## Requirements

- Because this package runs specific "elevated permissions" queries in order to provide a database and a database user, the user defined in ***database.php*** needs to have the ability to create users, databases, and grant privileges.

The following is a sample query to create a user with those privileges.

`CREATE DATABASE "tenancy";`

`CREATE ROLE "tenancy" SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD 'someRandomPassword';`

> Note: The above command is simply an example. It will grant full access to all databases to the ***tenancy*** user. Please consult your teams security professional.
- The ***affects-connections*** package is required by this package in order to transfer the database from one database user to another when the database user. This change occurs when the Tenant is updated causing a new database username within the ***hooks-database*** package.


## Installation
### Using Tenancy/Framework

Install via composer:

`composer require unspokenn/db-driver-pgsql`

### Using Tenancy/Tenancy or with provider discovery disabled

Register the following ServiceProvider:

- `Tenancy\Database\Drivers\Pgsql\Provider::class`

## Configuration

Detailed configuration steps are located in the ***hooks-database*** package and the ***affects-connections*** package.

In general there are several ways of setting the tenant database configuration for PgSql:

- Reusing a connection configured in ***database.php*** under ***connections***.
- Load a configuration array from a separate file.

### Example

In the example below we will configure the database to be created using the information from the ***pgsql*** database connection defined in the ***config/database.php*** and add Tenancy's default database settings to this.

`$event->useConnection('pgsql', $event->defaults($event->tenant));`