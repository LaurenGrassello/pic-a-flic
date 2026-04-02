<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402035016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create watchlist_movies table for saved matched movies';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE watchlist_movies (
            id INT AUTO_INCREMENT NOT NULL,
            watchlist_id INT NOT NULL,
            movie_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_WATCHLIST_MOVIE (watchlist_id, movie_id),
            INDEX IDX_WATCHLIST_MOVIES_WATCHLIST (watchlist_id),
            INDEX IDX_WATCHLIST_MOVIES_MOVIE (movie_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE watchlist_movies
            ADD CONSTRAINT FK_WATCHLIST_MOVIES_WATCHLIST
            FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_WATCHLIST_MOVIES_MOVIE
            FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE watchlist_movies DROP FOREIGN KEY FK_WATCHLIST_MOVIES_WATCHLIST");
        $this->addSql("ALTER TABLE watchlist_movies DROP FOREIGN KEY FK_WATCHLIST_MOVIES_MOVIE");
        $this->addSql("DROP TABLE watchlist_movies");
    }
}