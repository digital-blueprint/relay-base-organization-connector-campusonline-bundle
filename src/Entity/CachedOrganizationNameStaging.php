<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class CachedOrganizationNameStaging
{
    use CachedOrganizationNameTrait;

    public const TABLE_NAME = 'organization_names_staging';

    #[ORM\Id]
    #[ORM\JoinColumn(name: self::ORGANIZATION_UID, referencedColumnName: 'uid', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: CachedOrganizationStaging::class, inversedBy: 'names')]
    private ?CachedOrganizationStaging $organization = null;

    public function getOrganization(): ?CachedOrganizationStaging
    {
        return $this->organization;
    }

    public function setOrganization(?CachedOrganizationStaging $organization): void
    {
        $this->organization = $organization;
    }
}
