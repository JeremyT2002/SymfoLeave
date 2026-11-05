<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261105181705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /** MySQL beendet Transaktionen bei DDL implizit – daher ohne Transaktion ausführen */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // Live läuft MySQL (wie SymfoShop auf demselben Server), lokal und in Tests SQLite
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, lead_id INT DEFAULT NULL, INDEX IDX_C4E0A61F55458D (lead_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE absence_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL, requires_approval TINYINT NOT NULL, counts_against_vacation TINYINT NOT NULL, visible_to_colleagues TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, annual_vacation_days INT NOT NULL, team_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D649296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('CREATE TABLE absence (id INT AUTO_INCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, start_half_day TINYINT NOT NULL, end_half_day TINYINT NOT NULL, comment LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, working_days NUMERIC(5, 1) NOT NULL, decision_comment LONGTEXT DEFAULT NULL, decided_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, type_id INT NOT NULL, decided_by_id INT DEFAULT NULL, INDEX IDX_765AE0C995275AB8845CBB3E (start_date, end_date), INDEX IDX_765AE0C9A76ED395 (user_id), INDEX IDX_765AE0C9C54C8C93 (type_id), INDEX IDX_765AE0C9E26B496B (decided_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F55458D FOREIGN KEY (lead_id) REFERENCES `user` (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE SET NULL');
            $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9C54C8C93 FOREIGN KEY (type_id) REFERENCES absence_type (id)');
            $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9E26B496B FOREIGN KEY (decided_by_id) REFERENCES `user` (id) ON DELETE SET NULL');

            return;
        }

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, start_half_day BOOLEAN NOT NULL, end_half_day BOOLEAN NOT NULL, comment CLOB DEFAULT NULL, status VARCHAR(20) NOT NULL, working_days NUMERIC(5, 1) NOT NULL, decision_comment CLOB DEFAULT NULL, decided_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INTEGER NOT NULL, type_id INTEGER NOT NULL, decided_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_765AE0C9A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_765AE0C9C54C8C93 FOREIGN KEY (type_id) REFERENCES absence_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_765AE0C9E26B496B FOREIGN KEY (decided_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_765AE0C995275AB8845CBB3E ON absence (start_date, end_date)');
        $this->addSql('CREATE INDEX IDX_765AE0C9A76ED395 ON absence (user_id)');
        $this->addSql('CREATE INDEX IDX_765AE0C9C54C8C93 ON absence (type_id)');
        $this->addSql('CREATE INDEX IDX_765AE0C9E26B496B ON absence (decided_by_id)');
        $this->addSql('CREATE TABLE absence_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL, requires_approval BOOLEAN NOT NULL, counts_against_vacation BOOLEAN NOT NULL, visible_to_colleagues BOOLEAN NOT NULL)');
        $this->addSql('CREATE TABLE team (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, lead_id INTEGER DEFAULT NULL, CONSTRAINT FK_C4E0A61F55458D FOREIGN KEY (lead_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C4E0A61F55458D ON team (lead_id)');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) DEFAULT NULL, annual_vacation_days INTEGER NOT NULL, team_id INTEGER DEFAULT NULL, CONSTRAINT FK_8D93D649296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_8D93D649296CD8AE ON "user" (team_id)');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9A76ED395');
            $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9C54C8C93');
            $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9E26B496B');
            $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F55458D');
            $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649296CD8AE');
            $this->addSql('DROP TABLE absence');
            $this->addSql('DROP TABLE absence_type');
            $this->addSql('DROP TABLE team');
            $this->addSql('DROP TABLE `user`');

            return;
        }

        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE absence_type');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE "user"');
    }
}
