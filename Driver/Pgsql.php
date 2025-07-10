<?php

declare(strict_types=1);

/*
 * This file is part of the tenancy/tenancy package.
 *
 * Copyright Tenancy for Laravel
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://tenancy.dev
 * @see https://github.com/tenancy
 */

namespace Tenancy\Database\Drivers\Pgsql\Driver;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tenancy\Database\Drivers\Pgsql\Concerns\ManagesSystemConnection;
use Tenancy\Hooks\Database\Contracts\ProvidesDatabase;
use Tenancy\Hooks\Database\Events\Drivers as Events;
use Tenancy\Hooks\Database\Support\QueryManager;
use Tenancy\Identification\Contracts\Tenant;

class Pgsql implements ProvidesDatabase
{
    protected QueryManager $queryManager;

    /**
     * @throws BindingResolutionException
     */
    public function __construct()
    {
        $this->queryManager = App::make(QueryManager::class);
    }

    public function configure(Tenant $tenant): array
    {
        $config = [];

        event(new Events\Configuring($tenant, $config, $this));

        return $config;
    }

    public function create(Tenant $tenant): bool
    {
        $config = $this->configure($tenant);

        event(new Events\Creating($tenant, $config, $this));

        $result = $this->queryManager->setConnection($this->system($tenant))
            ->process(function () use ($config) {
                /** @var QueryManager $this */
                $this->statement("CREATE ROLE \"{$config['username']}\" WITH LOGIN PASSWORD '{$config['password']}'");
                $this->statement("CREATE DATABASE \"{$config['database']}\" OWNER \"{$config['username']}\"");
                $this->statement("GRANT ALL PRIVILEGES ON DATABASE \"{$config['database']}\" TO \"{$config['username']}\"");
            })
            ->getStatus();

        event(new Events\Created($tenant, $this, $result));

        return $result;
    }

    public function update(Tenant $tenant): bool
    {
        $config = $this->configure($tenant);

        event(new Events\Updating($tenant, $config, $this));

        if (!isset($config['oldUsername'])) {
            return false;
        }

        $result = $this->queryManager->setConnection($this->system($tenant))
            ->process(function () use ($config) {
                /** @var QueryManager $this */
                $this->statement("ALTER ROLE \"{$config['oldUsername']}\" RENAME TO \"{$config['username']}\"");
                $this->statement("ALTER ROLE \"{$config['username']}\" WITH PASSWORD '{$config['password']}'");
                $this->statement("ALTER DATABASE \"{$config['oldUsername']}\" RENAME TO \"{$config['database']}\"");
                $this->statement("ALTER DATABASE \"{$config['database']}\" OWNER TO \"{$config['username']}\"");
                $this->statement("GRANT ALL PRIVILEGES ON DATABASE \"{$config['database']}\" TO \"{$config['username']}\"");
            })
            ->getStatus();

        event(new Events\Updated($tenant, $this, $result));

        return $result;
    }

    public function delete(Tenant $tenant): bool
    {
        $config = $this->configure($tenant);

        event(new Events\Deleting($tenant, $config, $this));

        $result = $this->queryManager->setConnection($this->system($tenant))
            ->process(function () use ($config) {
                /** @var QueryManager $this */
                $this->statement("DROP ROLE IF EXISTS \"{$config['username']}\"");
                $this->statement("DROP DATABASE IF EXISTS \"{$config['database']}\"");
            })
            ->getStatus();

        event(new Events\Deleted($tenant, $this, $result));

        return $result;
    }

    protected function system(Tenant $tenant): ConnectionInterface
    {
        $connection = null;

        if (in_array(ManagesSystemConnection::class, class_implements($tenant))) {
            /** @var ManagesSystemConnection $tenant */
            $connection = $tenant->getManagingSystemConnection() ?? $connection;
        }

        return DB::connection($connection);
    }
}
