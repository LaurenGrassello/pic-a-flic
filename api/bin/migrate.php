<?php
declare(strict_types=1);

use PicaFlic\Bootstrap\AppBuilder;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;

require __DIR__ . '/../vendor/autoload.php';

$container = AppBuilder::buildContainer(dirname(__DIR__));
$em = $container->get(Doctrine\ORM\EntityManager::class);

$config = new ConfigurationArray([
    'table_storage'        => ['table_name' => 'doctrine_migration_versions'],
    'migrations_paths'     => ['DoctrineMigrations' => __DIR__ . '/../migrations'],
    'all_or_nothing'       => true,
    'check_database_platform' => false,
]);

$df = DependencyFactory::fromEntityManager($config, new ExistingEntityManager($em));
$df->getMetadataStorage()->ensureInitialized();

$version = $df->getVersionAliasResolver()->resolveVersionAlias('latest');
$plan    = $df->getMigrationPlanCalculator()->getPlanUntilVersion($version);

$items = $plan->getItems();
if (count($items) === 0) {
    echo "No migrations to execute.\n";
    exit(0);
}

$migrator = $df->getMigrator();

/**
 * Doctrine Migrations has two APIs across 3.x:
 *   - migrate(MigrationPlanList $plan, bool $dryRun)
 *   - migrate(MigrationPlanList $plan, MigratorConfiguration $config)
 * Use reflection to decide which to pass.
 */
$method = new ReflectionMethod($migrator, 'migrate');
$secondParam = $method->getParameters()[1] ?? null;
$type = $secondParam?->getType();
$name = ($type instanceof ReflectionNamedType) ? $type->getName() : null;

if ($name === 'bool') {
    // Older signature: pass dryRun=false
    $migrator->migrate($plan, false);
} else {
    // Newer signature: use MigratorConfiguration
    if (class_exists(\Doctrine\Migrations\MigratorConfiguration::class)) {
        $mc = new \Doctrine\Migrations\MigratorConfiguration();
        $mc->setDryRun(false);
        $mc->setAllOrNothing(true);
        $migrator->migrate($plan, $mc);
    } else {
        // Fallback: try bool
        $migrator->migrate($plan, false);
    }
}

echo "Applied " . count($items) . " migration(s).\n";