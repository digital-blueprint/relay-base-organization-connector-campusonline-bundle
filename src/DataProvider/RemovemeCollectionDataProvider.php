<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DataProvider;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Dbp\Relay\CoreBundle\Helpers\ArrayFullPaginator;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\Removeme;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\RemovemeProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class RemovemeCollectionDataProvider extends AbstractController implements CollectionDataProviderInterface, RestrictedDataProviderInterface
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

    public function getCollection(string $resourceClass, string $operationName = null, array $context = []): ArrayFullPaginator
    {
        $perPage = 30;
        $page = 1;

        $filters = $context['filters'] ?? [];
        if (isset($filters['page'])) {
            $page = (int) $filters['page'];
        }
        if (isset($filters['perPage'])) {
            $perPage = (int) $filters['perPage'];
        }

        return new ArrayFullPaginator($this->api->getRemovemes(), $page, $perPage);
    }
}
