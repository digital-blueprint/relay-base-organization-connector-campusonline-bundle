<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Migrations;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Doctrine\DBAL\Schema\Schema;

final class Version20251218113700 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return 'creates the organizations and organization_names, and the respective staging tables';
    }

    public function up(Schema $schema): void
    {
        $organizationsTable = CachedOrganization::TABLE_NAME;
        $uidColumn = CachedOrganization::UID;
        $codeColumn = CachedOrganization::CODE;
        $parentUidColumn = CachedOrganization::PARENT_UID;
        $groupKeyColumn = CachedOrganization::GROUP_KEY;
        $typeUidColumn = CachedOrganization::TYPE_UID;
        $addressStreetColumn = CachedOrganization::ADDRESS_STREET;
        $addressCityColumn = CachedOrganization::ADDRESS_CITY;
        $addressPostalCodeColumn = CachedOrganization::ADDRESS_POSTAL_CODE;
        $addressCountryColumn = CachedOrganization::ADDRESS_COUNTRY;

        $this->addSql(<<<STMT
            CREATE TABLE $organizationsTable (
                $uidColumn VARCHAR(16) NOT NULL,
                $codeColumn VARCHAR(16) DEFAULT NULL,
                $parentUidColumn VARCHAR(16) DEFAULT NULL,
                $groupKeyColumn VARCHAR(16) DEFAULT NULL,
                $typeUidColumn VARCHAR(16) DEFAULT NULL,
                $addressStreetColumn VARCHAR(256) DEFAULT NULL,
                $addressCityColumn VARCHAR(128) DEFAULT NULL,
                $addressPostalCodeColumn VARCHAR(16) DEFAULT NULL,
                $addressCountryColumn VARCHAR(128) DEFAULT NULL,
                PRIMARY KEY($uidColumn)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
            STMT);

        $organizationsStagingTable = CachedOrganization::STAGING_TABLE_NAME;

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

        $organizationNamesStagingTable = CachedOrganizationName::STAGING_TABLE_NAME;

        $this->addSql("CREATE TABLE $organizationNamesStagingTable LIKE $organizationNamesTable");
    }
}
