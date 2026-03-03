<?php
declare(strict_types=1);

use Doctrine\ORM\Tools\SchemaTool;
use PicaFlic\Bootstrap\AppBuilder;
use PicaFlic\Domain\Entity\PasswordReset;
use PicaFlic\Domain\Entity\UserProvider;
use PicaFlic\Domain\Entity\Provider;

require __DIR__ . '/../vendor/autoload.php';

$container = AppBuilder::buildContainer(dirname(__DIR__));
$em = $container->get(\Doctrine\ORM\EntityManagerInterface::class);

// Only update the new tables so we don't touch anything else.
$classes = [
    $em->getClassMetadata(PasswordReset::class),
    $em->getClassMetadata(UserProvider::class),
    $em->getClassMetadata(Provider::class), // needed for FK metadata
];

$tool = new SchemaTool($em);
$tool->updateSchema($classes, true); // safe mode: only add missing, don't drop
echo "✅ Schema updated for password_resets and user_providers.\n";