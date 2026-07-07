<?php
declare (strict_types = 1);

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use PicaFlic\Bootstrap\AppBuilder;

$container = AppBuilder::buildContainer(__DIR__);
$entityManager = $container->get(EntityManagerInterface::class);

return ConsoleRunner::createHelperSet($entityManager);