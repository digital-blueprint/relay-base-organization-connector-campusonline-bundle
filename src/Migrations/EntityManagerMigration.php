<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Migrations;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\DbpRelayBaseOrganizationConnectorCampusonlineExtension;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationNameStaging;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationStaging;
use Dbp\Relay\CoreBundle\Doctrine\AbstractEntityManagerMigration;

abstract class EntityManagerMigration extends AbstractEntityManagerMigration
{
    protected function getEntityManagerId(): string
    {
        return DbpRelayBaseOrganizationConnectorCampusonlineExtension::ENTITY_MANAGER_ID;
    }

    protected function recreateCacheTables(): void
    {
        $organizationsTable = CachedOrganization::TABLE_NAME;
        $uidColumn = CachedOrganization::UID;
        $codeColumn = CachedOrganization::CODE;
        $parentUidColumn = CachedOrganization::PARENT_UID;
        $groupKeyColumn = CachedOrganization::GROUP_KEY;
        $typeUidColumn = CachedOrganization::TYPE_UID;

        $this->addSql(<<<STMT
            CREATE TABLE $organizationsTable (
                $uidColumn VARCHAR(16) NOT NULL,
                $codeColumn VARCHAR(16) DEFAULT NULL,
                $parentUidColumn VARCHAR(16) DEFAULT NULL,
                $groupKeyColumn VARCHAR(16) DEFAULT NULL,
                $typeUidColumn VARCHAR(16) DEFAULT NULL,
                PRIMARY KEY($uidColumn)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            STMT);

        $organizationsStagingTable = CachedOrganizationStaging::TABLE_NAME;

        $this->addSql("CREATE TABLE $organizationsStagingTable LIKE $organizationsTable");

        $organizationNamesTable = CachedOrganizationName::TABLE_NAME;
        $organizationUidColumn = CachedOrganizationName::ORGANIZATION_UID;
        $languageTagColumn = CachedOrganizationName::LANGUAGE_TAG;
        $nameColumn = CachedOrganizationName::NAME;

        $this->addSql(<<<STMT
            CREATE TABLE $organizationNamesTable (
                $organizationUidColumn VARCHAR(16) NOT NULL,
                $languageTagColumn VARCHAR(2) NOT NULL,
                $nameColumn VARCHAR(255) NOT NULL,
                PRIMARY KEY($organizationUidColumn, $languageTagColumn)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            STMT);

        $organizationNamesStagingTable = CachedOrganizationNameStaging::TABLE_NAME;

        $this->addSql("CREATE TABLE $organizationNamesStagingTable LIKE $organizationNamesTable");
    }
}
