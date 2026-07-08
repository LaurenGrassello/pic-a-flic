<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708000356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add logo_path and last_seen_at columns to providers table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('providers');
        $table->addColumn('logo_path', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('last_seen_at', 'datetime', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('providers');
        $table->dropColumn('logo_path');
        $table->dropColumn('last_seen_at');
    }
}