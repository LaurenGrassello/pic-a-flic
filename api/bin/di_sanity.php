<?php
require __DIR__ . "/../vendor/autoload.php";
$c = \PicaFlic\Bootstrap\AppBuilder::buildContainer(dirname(__DIR__));
$em   = $c->get(\Doctrine\ORM\EntityManagerInterface::class);
$repo = $c->get(\PicaFlic\Domain\Repository\MovieRepository::class);
echo "EM:   " . get_class($em) . PHP_EOL;
echo "Repo: " . get_class($repo) . PHP_EOL;
