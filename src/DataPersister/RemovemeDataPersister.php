<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DataPersister;

use ApiPlatform\Core\DataPersister\DataPersisterInterface;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\Removeme;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\RemovemeProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class RemovemeDataPersister extends AbstractController implements DataPersisterInterface
{
    private $api;

    public function __construct(RemovemeProviderInterface $api)
    {
        $this->api = $api;
    }

    public function supports($data): bool
    {
        return $data instanceof Removeme;
    }

    public function persist($data): void
    {
        // TODO
    }

    public function remove($data)
    {
        // TODO
    }
}
