<?php
declare(strict_types=1);
use PicaFlic\Bootstrap\AppBuilder;
use Doctrine\ORM\EntityManager;
use PicaFlic\Domain\Entity\StreamingService;
require __DIR__.'/../vendor/autoload.php';
$container = AppBuilder::buildContainer(dirname(__DIR__));
$em = $container->get(Doctrine\ORM\EntityManager::class);
$repo = $em->getRepository(StreamingService::class);
$seed=[['netflix','Netflix'],['prime','Amazon Prime Video'],['hulu','Hulu'],['disney','Disney+'],['max','Max'],['apple','Apple TV+']];
foreach($seed as [$code,$name]){ if(!$repo->findOneBy(['code'=>$code])) $em->persist(new StreamingService($code,$name)); }
$em->flush(); echo "Seeded streaming services.\n";
