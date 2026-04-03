<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402195624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_movie_preferences and watchlist_swipes tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE user_movie_preferences (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            movie_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_USER_MOVIE_PREF (user_id, movie_id),
            INDEX IDX_USER_MOVIE_PREF_USER (user_id),
            INDEX IDX_USER_MOVIE_PREF_MOVIE (movie_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE watchlist_swipes (
            id INT AUTO_INCREMENT NOT NULL,
            watchlist_id INT NOT NULL,
            user_id INT NOT NULL,
            movie_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_WATCHLIST_SWIPE (watchlist_id, user_id, movie_id),
            INDEX IDX_WATCHLIST_SWIPES_WATCHLIST (watchlist_id),
            INDEX IDX_WATCHLIST_SWIPES_USER (user_id),
            INDEX IDX_WATCHLIST_SWIPES_MOVIE (movie_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE user_movie_preferences
            ADD CONSTRAINT FK_USER_MOVIE_PREF_USER
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_USER_MOVIE_PREF_MOVIE
            FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE");

        $this->addSql("ALTER TABLE watchlist_swipes
            ADD CONSTRAINT FK_WATCHLIST_SWIPES_WATCHLIST
            FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_WATCHLIST_SWIPES_USER
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_WATCHLIST_SWIPES_MOVIE
            FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE watchlist_swipes DROP FOREIGN KEY FK_WATCHLIST_SWIPES_WATCHLIST");
        $this->addSql("ALTER TABLE watchlist_swipes DROP FOREIGN KEY FK_WATCHLIST_SWIPES_USER");
        $this->addSql("ALTER TABLE watchlist_swipes DROP FOREIGN KEY FK_WATCHLIST_SWIPES_MOVIE");

        $this->addSql("ALTER TABLE user_movie_preferences DROP FOREIGN KEY FK_USER_MOVIE_PREF_USER");
        $this->addSql("ALTER TABLE user_movie_preferences DROP FOREIGN KEY FK_USER_MOVIE_PREF_MOVIE");

        $this->addSql("DROP TABLE watchlist_swipes");
        $this->addSql("DROP TABLE user_movie_preferences");
    }
}