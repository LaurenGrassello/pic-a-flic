<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707233915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add genre_ids column to movies table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('movies');
        $table->addColumn('genre_ids', 'string', ['length' => 255, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('movies');
        $table->dropColumn('genre_ids');
    }
}