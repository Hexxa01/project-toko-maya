<?php

namespace App\Foundation\Bootstrap;

use Illuminate\Foundation\Bootstrap\LoadConfiguration as FrameworkLoadConfiguration;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class LoadConfiguration extends FrameworkLoadConfiguration
{
    protected function getBaseConfiguration()
    {
        $config = [];

        // Laravel 11 evaluates every framework config before merging application files.
        // Its database defaults still reference a PDO constant deprecated in PHP 8.5.
        // This application supplies the complete database configuration itself.
        // Keep all other framework defaults (view, hashing, etc.) unchanged.
        foreach (Finder::create()->files()->name('*.php')->notName('database.php')->in($this->frameworkConfigPath()) as $file) {
            $config[basename($file->getRealPath(), '.php')] = require $file->getRealPath();
        }

        return $config;
    }

    protected function frameworkConfigPath(): string
    {
        return dirname((new ReflectionClass(FrameworkLoadConfiguration::class))->getFileName(), 5).'/config';
    }
}
