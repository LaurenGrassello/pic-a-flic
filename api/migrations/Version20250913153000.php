<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds follow & swipe tables for social and “matches” features.
 */
final class Version20250913153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create follow (user->user) and swipe (user->movie) tables';
    }

    public function up(Schema $schema): void
    {
        // follow
        $this->addSql("CREATE TABLE follow (
            id INT AUTO_INCREMENT NOT NULL,
            follower_id INT NOT NULL,
            followee_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_FOLLOW_PAIR (follower_id, followee_id),
            INDEX IDX_FOLLOW_FOLLOWER (follower_id),
            INDEX IDX_FOLLOW_FOLLOWEE (followee_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // swipe
        $this->addSql("CREATE TABLE swipe (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            movie_id INT NOT NULL,
            liked TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_SWIPE_USER_MOVIE (user_id, movie_id),
            INDEX IDX_SWIPE_USER (user_id),
            INDEX IDX_SWIPE_MOVIE (movie_id),
            INDEX IDX_SWIPE_USER_LIKED (user_id, liked),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        // FKs
        $this->addSql("ALTER TABLE follow
            ADD CONSTRAINT FK_FOLLOW_FOLLOWER FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_FOLLOW_FOLLOWEE FOREIGN KEY (followee_id) REFERENCES users (id) ON DELETE CASCADE");

        $this->addSql("ALTER TABLE swipe
            ADD CONSTRAINT FK_SWIPE_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_SWIPE_MOVIE FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE follow DROP FOREIGN KEY FK_FOLLOW_FOLLOWER");
        $this->addSql("ALTER TABLE follow DROP FOREIGN KEY FK_FOLLOW_FOLLOWEE");
        $this->addSql("ALTER TABLE swipe DROP FOREIGN KEY FK_SWIPE_USER");
        $this->addSql("ALTER TABLE swipe DROP FOREIGN KEY FK_SWIPE_MOVIE");
        $this->addSql("DROP TABLE swipe");
        $this->addSql("DROP TABLE follow");
    }
}