<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927160157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track when issues regress';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue ADD regressed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE issue DROP regressed_at');
    }
}
