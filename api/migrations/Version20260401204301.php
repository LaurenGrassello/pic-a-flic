<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401204301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create watchlists and watchlist_members tables for shared watchlists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE watchlists (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_WATCHLISTS_CREATED_BY (created_by),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE watchlist_members (
            id INT AUTO_INCREMENT NOT NULL,
            watchlist_id INT NOT NULL,
            user_id INT NOT NULL,
            joined_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_WATCHLIST_MEMBER (watchlist_id, user_id),
            INDEX IDX_WATCHLIST_MEMBERS_WATCHLIST (watchlist_id),
            INDEX IDX_WATCHLIST_MEMBERS_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE watchlists
            ADD CONSTRAINT FK_WATCHLISTS_CREATED_BY
            FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE");

        $this->addSql("ALTER TABLE watchlist_members
            ADD CONSTRAINT FK_WATCHLIST_MEMBERS_WATCHLIST
            FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_WATCHLIST_MEMBERS_USER
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE watchlist_members DROP FOREIGN KEY FK_WATCHLIST_MEMBERS_WATCHLIST");
        $this->addSql("ALTER TABLE watchlist_members DROP FOREIGN KEY FK_WATCHLIST_MEMBERS_USER");
        $this->addSql("ALTER TABLE watchlists DROP FOREIGN KEY FK_WATCHLISTS_CREATED_BY");
        $this->addSql("DROP TABLE watchlist_members");
        $this->addSql("DROP TABLE watchlists");
    }
}