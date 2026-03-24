<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Migrations;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationNameStaging;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationStaging;
use Doctrine\DBAL\Schema\Schema;

final class Version20260319115600 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'adds foreign key constraint between organizations and organization_names (and the respective staging tables)';
    }

    public function up(Schema $schema): void
    {
        $organizationsTable = CachedOrganization::TABLE_NAME;
        $organizationNamesTable = CachedOrganizationName::TABLE_NAME;

        $organizationsStagingTable = CachedOrganizationStaging::TABLE_NAME;
        $organizationNamesStagingTable = CachedOrganizationNameStaging::TABLE_NAME;

        $uidColumn = CachedOrganization::UID;
        $organizationUidColumn = CachedOrganizationName::ORGANIZATION_UID;

        $this->addSql(<<<STMT
            ALTER TABLE $organizationNamesTable
            ADD CONSTRAINT FK_organization_names_organizations FOREIGN KEY ($organizationUidColumn) REFERENCES $organizationsTable ($uidColumn) ON DELETE CASCADE
            STMT);

        $this->addSql(<<<STMT
            ALTER TABLE $organizationNamesStagingTable
            ADD CONSTRAINT FK_organization_names_staging_organizations_staging FOREIGN KEY ($organizationUidColumn) REFERENCES $organizationsStagingTable ($uidColumn) ON DELETE CASCADE
            STMT);
    }
}
