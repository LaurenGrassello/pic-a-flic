<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708000826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add overview, popularity, recommendationScore, similarityScore to movies; make poster_path nullable';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('movies');
        $table->addColumn('overview', 'text', ['notnull' => false]);
        $table->addColumn('popularity', 'integer', ['notnull' => false]);
        $table->addColumn('recommendationScore', 'integer', ['notnull' => false]);
        $table->addColumn('similarityScore', 'decimal', ['precision' => 5, 'scale' => 2, 'notnull' => false]);
        $table->modifyColumn('poster_path', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('movies');
        $table->dropColumn('overview');
        $table->dropColumn('popularity');
        $table->dropColumn('recommendationScore');
        $table->dropColumn('similarityScore');
        $table->modifyColumn('poster_path', ['notnull' => true]);
    }
}