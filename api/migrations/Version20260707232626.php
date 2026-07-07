<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707232626 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create title_providers table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('title_providers');
        $table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('tmdb_id', 'integer');
        $table->addColumn('is_tv', 'boolean', ['default' => false]);
        $table->addColumn('provider_id', 'integer');
        $table->addColumn('region', 'string', ['length' => 2, 'default' => 'US']);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['tmdb_id', 'is_tv', 'provider_id', 'region'], 'uniq_title_provider_region');
        $table->addIndex(['tmdb_id', 'is_tv'], 'idx_title_providers_tmdb');
        $table->addIndex(['provider_id', 'region'], 'idx_title_providers_provider');
        $table->addIndex(['provider_id', 'region', 'is_tv'], 'idx_tp_provider_region_is');
        $table->addIndex(['tmdb_id', 'is_tv'], 'idx_tp_tmdb_is');
        $table->addForeignKeyConstraint('providers', ['provider_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_title_providers_provider');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('title_providers');
    }
}