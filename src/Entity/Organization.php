<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Dbp\Relay\BaseOrganizationBundle\Entity\Organization as BaseOrganization;

class Organization extends BaseOrganization
{
    private $code;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }
}
