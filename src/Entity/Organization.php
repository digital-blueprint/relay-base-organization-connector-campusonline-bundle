<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Dbp\Relay\BaseOrganizationBundle\Entity\Organization as BaseOrganization;

class Organization extends BaseOrganization
{
    private $code;

    private $type;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }
}
