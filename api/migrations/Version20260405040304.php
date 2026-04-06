<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405040304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_streaming_services table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE user_streaming_services (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            provider_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_USER_PROVIDER (user_id, provider_id),
            INDEX IDX_USER_STREAMING_SERVICES_USER (user_id),
            INDEX IDX_USER_STREAMING_SERVICES_PROVIDER (provider_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE user_streaming_services
            ADD CONSTRAINT FK_USER_STREAMING_SERVICES_USER
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_USER_STREAMING_SERVICES_PROVIDER
            FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user_streaming_services DROP FOREIGN KEY FK_USER_STREAMING_SERVICES_USER");
        $this->addSql("ALTER TABLE user_streaming_services DROP FOREIGN KEY FK_USER_STREAMING_SERVICES_PROVIDER");
        $this->addSql("DROP TABLE user_streaming_services");
    }
}