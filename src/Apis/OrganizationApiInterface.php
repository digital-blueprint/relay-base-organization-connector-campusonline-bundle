<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis;

use Dbp\CampusonlineApi\Helpers\ApiException;

interface OrganizationApiInterface
{
    /**
     * @throws ApiException
     */
    public function checkConnection(): void;

    /**
     * @throws ApiException
     */
    public function getOrganizationById(string $identifier, array $options = []): OrganizationAndExtraData;

    /**
     * @return iterable<OrganizationAndExtraData>
     *
     * @throws ApiException
     */
    public function getOrganizations(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): iterable;

    public function setClientHandler(?object $handler): void;

    public function recreateOrganizationsCache(): void;

    /**
     * @param callable(mixed $organizationAndExtraData): array $isOrganizationCallback
     */
    public function setIsOrganizationCallback(callable $isOrganizationCallback): void;
}
