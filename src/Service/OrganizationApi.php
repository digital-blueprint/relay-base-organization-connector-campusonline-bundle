<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\LegacyWebService\Api;
use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitData;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\Organization;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class OrganizationApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /*
     * @var Api
     */
    private $api;
    private $config;
    private $clientHandler;
    private $cachePool;
    private $cacheTTL;

    public function __construct()
    {
        $this->config = [];
    }

    public function setCache(?CacheItemPoolInterface $cachePool, int $ttl)
    {
        $this->cachePool = $cachePool;
        $this->cacheTTL = $ttl;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function setClientHandler(?object $handler)
    {
        $this->clientHandler = $handler;
        if ($this->api !== null) {
            $this->api->setClientHandler($handler);
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        if ($this->api !== null) {
            $this->api->setLogger($logger);
        }
    }

    /**
     * @throws ApiException
     */
    public function checkConnection()
    {
        $this->getApi()->OrganizationUnit()->getOrganizationUnits();
    }

    /**
     * @throws ApiException
     */
    public function getOrganizationById(string $identifier, array $options = []): ?Organization
    {
        $orgUnitData = $this->getApi()->OrganizationUnit()->getOrganizationUnitById($identifier, $options);

        return $orgUnitData ? self::toOrganization($orgUnitData) : null;
    }

    /**
     * @throws ApiException
     */
    public function getOrganizations(array $options = []): array
    {
        $orgs = [];
        foreach ($this->getApi()->OrganizationUnit()->getOrganizationUnits($options) as $orgUnit) {
            $orgs[] = self::toOrganization($orgUnit);
        }

        return $orgs;
    }

    private function getApi(): Api
    {
        if ($this->api === null) {
            $accessToken = $this->config['api_token'] ?? '';
            $baseUrl = $this->config['api_url'] ?? '';
            $rootOrgUnitId = $this->config['org_root_id'] ?? '';

            $this->api = new Api($baseUrl, $accessToken, $rootOrgUnitId,
                $this->logger, $this->cachePool, $this->cacheTTL, $this->clientHandler);
        }

        return $this->api;
    }

    private static function toOrganization(OrganizationUnitData $orgUnit): Organization
    {
        $organization = new Organization();
        $organization->setIdentifier($orgUnit->getIdentifier());
        $organization->setName($orgUnit->getName());
        $organization->setAlternateName('F'.$orgUnit->getCode());
        $organization->setUrl($orgUnit->getUrl());
        $organization->setCode($orgUnit->getCode());

        return $organization;
    }
}
