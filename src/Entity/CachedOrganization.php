<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class CachedOrganization
{
    use CachedOrganizationTrait;

    public const TABLE_NAME = 'organizations';

    #[ORM\OneToMany(targetEntity: CachedOrganizationName::class, mappedBy: 'organization')]
    private Collection $names;

    public function __construct()
    {
        $this->names = new ArrayCollection();
    }

    public function getNames(): Collection
    {
        return $this->names;
    }

    public function setNames(Collection $names): void
    {
        $this->names = $names;
    }
}
