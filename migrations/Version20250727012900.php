<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Изменение типа поля telegram_id с integer на bigint
 */
final class Version20250727012900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change telegram_id type from integer to bigint';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN telegram_id TYPE BIGINT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER COLUMN telegram_id TYPE INTEGER');
    }
}
