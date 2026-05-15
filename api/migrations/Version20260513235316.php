<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260513235316 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE personal_watchlists (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $this->addSql("
            CREATE TABLE personal_watchlist_movies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                watchlist_id INT NOT NULL,
                movie_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_watchlist_movie (watchlist_id, movie_id),
                FOREIGN KEY (watchlist_id) REFERENCES personal_watchlists(id) ON DELETE CASCADE,
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE personal_watchlist_movies");
        $this->addSql("DROP TABLE personal_watchlists");
    }
}