<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20260319115600 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'creates the organizations and organization_names, and the respective staging tables';
    }

    public function up(Schema $schema): void
    {
        // nothing to do
    }
}
