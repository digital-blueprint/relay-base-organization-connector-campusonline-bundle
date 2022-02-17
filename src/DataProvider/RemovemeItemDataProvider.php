<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\Removeme;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\RemovemeProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class RemovemeItemDataProvider extends AbstractController implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private $api;

    public function __construct(RemovemeProviderInterface $api)
    {
        $this->api = $api;
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return Removeme::class === $resourceClass;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?Removeme
    {
        return $this->api->getRemovemeById($id);
    }
}
