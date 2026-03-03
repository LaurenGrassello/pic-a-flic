<?php
declare(strict_types=1);
use PicaFlic\Bootstrap\AppBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\EntityManager;
require __DIR__.'/../vendor/autoload.php';
$container = AppBuilder::buildContainer(dirname(__DIR__));
$em = $container->get(Doctrine\ORM\EntityManager::class);
$classes = $em->getMetadataFactory()->getAllMetadata();
$tool = new SchemaTool($em); $tool->createSchema($classes);
echo "Schema created from entities.\n";
