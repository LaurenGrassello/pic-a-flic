<?php
declare(strict_types=1);

use PicaFlic\Bootstrap\AppBuilder;
use Doctrine\ORM\EntityManager;
use PicaFlic\Domain\Entity\User;
use PicaFlic\Domain\Entity\Movie;
use PicaFlic\Domain\Entity\Swipe;

require __DIR__ . '/../vendor/autoload.php';

$container = AppBuilder::buildContainer(dirname(__DIR__));
/** @var EntityManager $em */
$em = $container->get(Doctrine\ORM\EntityManager::class);

// ensure a second user
$friend = $em->getRepository(User::class)->findOneBy(['email' => 'friend@example.com']);
if (!$friend) {
    $friend = new User('friend@example.com', password_hash('pass1234', PASSWORD_ARGON2ID), 'Friend');
    $em->persist($friend);
    $em->flush();
}

// like the sample movie (Matrix) for both users
$movie = $em->getRepository(Movie::class)->findOneBy(['tmdbId' => 603]);
if ($movie) {
    // find Dev
    $me = $em->getRepository(User::class)->findOneBy(['email' => 'dev@example.com']);
    foreach ([$me, $friend] as $u) {
        if ($u) {
            $existing = $em->getRepository(Swipe::class)->findOneBy(['user'=>$u, 'movie'=>$movie]);
            if (!$existing) $em->persist(new Swipe($u, $movie, true));
        }
    }
    $em->flush();
}

echo "Seeded friend user and likes.\n";