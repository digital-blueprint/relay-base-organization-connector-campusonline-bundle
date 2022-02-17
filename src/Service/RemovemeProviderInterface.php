<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\Removeme;

interface RemovemeProviderInterface
{
    public function getRemovemeById(string $identifier): ?Removeme;

    public function getRemovemes(): array;
}
