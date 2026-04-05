<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404193531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create watchlist_invites table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE watchlist_invites (
            id INT AUTO_INCREMENT NOT NULL,
            watchlist_id INT NOT NULL,
            invited_user_id INT NOT NULL,
            invited_by_user_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_WATCHLIST_INVITE (watchlist_id, invited_user_id),
            INDEX IDX_WATCHLIST_INVITES_WATCHLIST (watchlist_id),
            INDEX IDX_WATCHLIST_INVITES_INVITED_USER (invited_user_id),
            INDEX IDX_WATCHLIST_INVITES_INVITED_BY (invited_by_user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE watchlist_invites
            ADD CONSTRAINT FK_WATCHLIST_INVITES_WATCHLIST
            FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_WATCHLIST_INVITES_INVITED_USER
            FOREIGN KEY (invited_user_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_WATCHLIST_INVITES_INVITED_BY
            FOREIGN KEY (invited_by_user_id) REFERENCES users (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE watchlist_invites DROP FOREIGN KEY FK_WATCHLIST_INVITES_WATCHLIST");
        $this->addSql("ALTER TABLE watchlist_invites DROP FOREIGN KEY FK_WATCHLIST_INVITES_INVITED_USER");
        $this->addSql("ALTER TABLE watchlist_invites DROP FOREIGN KEY FK_WATCHLIST_INVITES_INVITED_BY");
        $this->addSql("DROP TABLE watchlist_invites");
    }
}