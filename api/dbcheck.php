<?php
require __DIR__ . "/vendor/autoload.php";

$container = \PicaFlic\Bootstrap\AppBuilder::buildContainer(__DIR__);
$settings  = $container->get("settings");
var_export($settings["db"]);
echo PHP_EOL;

try {
  $em = $container->get(\Doctrine\ORM\EntityManager::class);
  $em->getConnection()->connect();
  echo "Doctrine DB connect: OK\n";
} catch (\Throwable $e) {
  echo "Doctrine DB connect: FAIL: " . $e->getMessage() . PHP_EOL;
}
