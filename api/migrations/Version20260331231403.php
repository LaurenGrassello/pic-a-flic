<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds friendships table for friend requests and accepted friends.
 */
final class Version20260331231403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create friendships table for friend requests and accepted friendships';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE friendships (
            id INT AUTO_INCREMENT NOT NULL,
            requester_id INT NOT NULL,
            addressee_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_FRIENDSHIP_PAIR (requester_id, addressee_id),
            INDEX IDX_FRIENDSHIPS_REQUESTER (requester_id),
            INDEX IDX_FRIENDSHIPS_ADDRESSEE (addressee_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE friendships
            ADD CONSTRAINT FK_FRIENDSHIPS_REQUESTER FOREIGN KEY (requester_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_FRIENDSHIPS_ADDRESSEE FOREIGN KEY (addressee_id) REFERENCES users (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE friendships DROP FOREIGN KEY FK_FRIENDSHIPS_REQUESTER");
        $this->addSql("ALTER TABLE friendships DROP FOREIGN KEY FK_FRIENDSHIPS_ADDRESSEE");
        $this->addSql("DROP TABLE friendships");
    }
}