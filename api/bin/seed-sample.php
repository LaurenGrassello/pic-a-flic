<?php
declare(strict_types=1);

/**
 * Dev helper: seed one movie + availability for demo.
 * Usage: docker compose exec app php bin/seed-sample.php
 */
use PicaFlic\Bootstrap\AppBuilder;
use Doctrine\ORM\EntityManager;
use PicaFlic\Domain\Entity\Movie;
use PicaFlic\Domain\Entity\StreamingService;
use PicaFlic\Domain\Entity\MovieAvailability;

require __DIR__ . '/../vendor/autoload.php';

$container = AppBuilder::buildContainer(dirname(__DIR__));
/** @var EntityManager $em */
$em = $container->get(Doctrine\ORM\EntityManager::class);

$movie = $em->getRepository(Movie::class)->findOneBy(['tmdbId' => 603]);
if (!$movie) {
    $movie = new Movie(603, 'The Matrix');
    $em->persist($movie);
}

$svc = $em->getRepository(StreamingService::class)->findOneBy(['code' => 'netflix']);
if ($svc) {
    // Add US availability if missing
    $exists = $em->getRepository(MovieAvailability::class)->findOneBy([
        'movie' => $movie, 'service' => $svc, 'region' => 'US'
    ]);
    if (!$exists) {
        $em->persist(new MovieAvailability($movie, $svc, 'US'));
    }
}

$em->flush();
echo "Seeded sample movie and availability.\n";