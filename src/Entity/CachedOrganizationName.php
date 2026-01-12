<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class CachedOrganizationName
{
    public const TABLE_NAME = 'organization_names';
    public const STAGING_TABLE_NAME = 'organization_names_staging';

    public const ORGANIZATION_UID = 'organizationUid';
    public const LANGUAGE_TAG = 'languageTag';
    public const NAME = 'name';

    #[ORM\Id]
    #[ORM\JoinColumn(name: self::ORGANIZATION_UID, referencedColumnName: 'uid', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: CachedOrganization::class, inversedBy: 'names')]
    private ?CachedOrganization $organization = null;

    #[ORM\Id]
    #[ORM\Column(name: self::LANGUAGE_TAG, type: 'string', length: 2)]
    private ?string $languageTag = null;

    #[ORM\Column(name: self::NAME, type: 'string', length: 255)]
    private ?string $name = null;

    public function getOrganization(): ?CachedOrganization
    {
        return $this->organization;
    }

    public function setOrganization(?CachedOrganization $organization): void
    {
        $this->organization = $organization;
    }

    public function getLanguageTag(): ?string
    {
        return $this->languageTag;
    }

    public function setLanguageTag(?string $languageTag): void
    {
        $this->languageTag = $languageTag;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
