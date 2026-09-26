<?php

declare (strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260926143028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create personal_watchlist_shares and personal_watchlist_swipes tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE personal_watchlist_shares (
                id INT AUTO_INCREMENT NOT NULL,
                watchlist_id INT NOT NULL,
                owner_user_id INT NOT NULL,
                shared_with_user_id INT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_watchlist_recipient (watchlist_id, shared_with_user_id),
                INDEX idx_pws_recipient (shared_with_user_id),
                INDEX idx_pws_owner (owner_user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        ');

        $this->addSql('
            CREATE TABLE personal_watchlist_swipes (
                id INT AUTO_INCREMENT NOT NULL,
                share_id INT NOT NULL,
                movie_id INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_share_movie (share_id, movie_id),
                INDEX idx_pwsw_share (share_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        ');

        $this->addSql('
            ALTER TABLE personal_watchlist_shares
            ADD CONSTRAINT fk_pws_watchlist FOREIGN KEY (watchlist_id) REFERENCES personal_watchlists (id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_pws_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_pws_recipient FOREIGN KEY (shared_with_user_id) REFERENCES users (id) ON DELETE CASCADE
        ');

        $this->addSql('
            ALTER TABLE personal_watchlist_swipes
            ADD CONSTRAINT fk_pwsw_share FOREIGN KEY (share_id) REFERENCES personal_watchlist_shares (id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_pwsw_movie FOREIGN KEY (movie_id) REFERENCES movies (id) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE personal_watchlist_swipes');
        $this->addSql('DROP TABLE personal_watchlist_shares');
    }
}