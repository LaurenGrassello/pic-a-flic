<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Baseline migration — no schema changes.
 * This lets us start using Doctrine Migrations from the current DB state.
 */
final class Version20250913214223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Baseline (no-op)";
    }

    public function up(Schema $schema): void
    {
        // no-op
    }

    public function down(Schema $schema): void
    {
        // no-op
    }
}
