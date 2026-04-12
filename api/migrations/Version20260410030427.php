<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260410030427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add provider_id to streaming_services and seed curated services';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE streaming_services ADD provider_id INT NOT NULL");

        // Preserve existing IDs 1-6 and clean names
        $this->addSql("UPDATE streaming_services SET name = 'Netflix', code = 'netflix', provider_id = 8 WHERE id = 1");
        $this->addSql("UPDATE streaming_services SET name = 'Prime Video', code = 'prime_video', provider_id = 9 WHERE id = 2");
        $this->addSql("UPDATE streaming_services SET name = 'Hulu', code = 'hulu', provider_id = 15 WHERE id = 3");
        $this->addSql("UPDATE streaming_services SET name = 'Disney+', code = 'disney_plus', provider_id = 337 WHERE id = 4");
        $this->addSql("UPDATE streaming_services SET name = 'Max', code = 'max', provider_id = 1899 WHERE id = 5");
        $this->addSql("UPDATE streaming_services SET name = 'Apple TV+', code = 'apple_tv_plus', provider_id = 350 WHERE id = 6");

        // Add new curated services
        $this->addSql("INSERT INTO streaming_services (id, name, code, provider_id) VALUES (7, 'Tubi', 'tubi', 73)");
        $this->addSql("INSERT INTO streaming_services (id, name, code, provider_id) VALUES (8, 'Peacock', 'peacock', 386)");
        $this->addSql("INSERT INTO streaming_services (id, name, code, provider_id) VALUES (9, 'Shudder', 'shudder', 99)");

        $this->addSql("CREATE UNIQUE INDEX UNIQ_STREAMING_SERVICES_PROVIDER_ID ON streaming_services (provider_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX UNIQ_STREAMING_SERVICES_PROVIDER_ID ON streaming_services");

        $this->addSql("DELETE FROM streaming_services WHERE id IN (7, 8, 9)");

        $this->addSql("UPDATE streaming_services SET name = 'Netflix', provider_id = 0 WHERE id = 1");
        $this->addSql("UPDATE streaming_services SET name = 'Amazon Prime Video', provider_id = 0 WHERE id = 2");
        $this->addSql("UPDATE streaming_services SET name = 'Hulu', provider_id = 0 WHERE id = 3");
        $this->addSql("UPDATE streaming_services SET name = 'Disney+', provider_id = 0 WHERE id = 4");
        $this->addSql("UPDATE streaming_services SET name = 'Max', provider_id = 0 WHERE id = 5");
        $this->addSql("UPDATE streaming_services SET name = 'Apple TV+', provider_id = 0 WHERE id = 6");

        $this->addSql("ALTER TABLE streaming_services DROP COLUMN provider_id");
    }
}