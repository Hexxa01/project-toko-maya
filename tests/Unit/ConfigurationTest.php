<?php

namespace Tests\Unit;

use App\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadConfiguration as FrameworkLoadConfiguration;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_application_uses_compatibility_loader_and_keeps_framework_defaults(): void
    {
        $this->assertInstanceOf(LoadConfiguration::class, $this->app->make(FrameworkLoadConfiguration::class));
        $this->assertSame('bcrypt', config('hashing.driver'));
        $this->assertNotEmpty(config('view.compiled'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertArrayHasKey('mysql', config('database.connections'));
        $this->assertArrayHasKey('mariadb', config('database.connections'));
    }

    public function test_framework_database_file_is_not_evaluated_but_other_defaults_are_loaded(): void
    {
        $directory = sys_get_temp_dir().'/maya-config-'.bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory.'/database.php', '<?php throw new \\RuntimeException("Deprecated framework defaults must not execute");');
        file_put_contents($directory.'/hashing.php', '<?php return ["driver" => "bcrypt"];');

        try {
            $loader = new class($directory) extends LoadConfiguration
            {
                public function __construct(private string $directory) {}

                protected function frameworkConfigPath(): string
                {
                    return $this->directory;
                }

                public function defaults(): array
                {
                    return $this->getBaseConfiguration();
                }
            };

            $this->assertSame(['hashing' => ['driver' => 'bcrypt']], $loader->defaults());
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_mysql_and_mariadb_keep_the_configured_ca_option_when_driver_is_loaded(): void
    {
        $repository = Env::getRepository();
        $previous = $repository->get('MYSQL_ATTR_SSL_CA');
        $repository->set('MYSQL_ATTR_SSL_CA', '/test/ca.pem');

        try {
            $database = require base_path('config/database.php');
            $expected = extension_loaded('pdo_mysql')
                ? [constant(defined('Pdo\\Mysql::ATTR_SSL_CA') ? 'Pdo\\Mysql::ATTR_SSL_CA' : 'PDO::MYSQL_ATTR_SSL_CA') => '/test/ca.pem']
                : [];

            $this->assertSame($expected, $database['connections']['mysql']['options']);
            $this->assertSame($expected, $database['connections']['mariadb']['options']);
        } finally {
            if ($previous === null) {
                $repository->clear('MYSQL_ATTR_SSL_CA');
            } else {
                $repository->set('MYSQL_ATTR_SSL_CA', $previous);
            }
        }
    }
}
