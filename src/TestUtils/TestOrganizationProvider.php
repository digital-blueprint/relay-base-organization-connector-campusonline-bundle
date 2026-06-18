<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\TestUtils;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\Configuration;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\DbpRelayBaseOrganizationConnectorCampusonlineExtension;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationEventSubscriber;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\TestUtils\TestEntityManager;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TestOrganizationProvider extends OrganizationProvider
{
    public const ORGANIZATION_CODE_ATTRIBUTE_NAME = 'code';
    public const TYPE_ATTRIBUTE_NAME = 'type';
    public const TYPE_NAME_ATTRIBUTE_NAME = 'typeName';

    private const CONFIG = [
        Configuration::CAMPUS_ONLINE_NODE => [
            'base_url' => 'https://campusonline.at/campusonline/ws/public/rest/',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ],
        'local_data_mapping' => [
            [
                'local_data_attribute' => self::ORGANIZATION_CODE_ATTRIBUTE_NAME,
                'source_attribute' => OrganizationEventSubscriber::CODE_SOURCE_ATTRIBUTE,
                'default_value' => '',
            ],
            [
                'local_data_attribute' => self::TYPE_ATTRIBUTE_NAME,
                'source_attribute' => OrganizationEventSubscriber::TYPE_UID_SOURCE_ATTRIBUTE,
                'default_value' => '',
            ],
            [
                'local_data_attribute' => self::TYPE_NAME_ATTRIBUTE_NAME,
                'source_attribute' => OrganizationEventSubscriber::TYPE_NAME_SOURCE_DATA_ATTRIBUTE,
                'default_value' => '',
            ],
        ],
    ];

    /**
     * @throws \Throwable
     */
    public static function createTestOrganizationProvider(
        ContainerInterface $container,
        array $organizationEventSubscribers = [],
        ?array $localDataMappingConfig = null,
        ?array $testOrganizationResources = null,
        ?CacheItemPoolInterface $campusonlineApiCacheItemPool = null,
        bool $mockAuthServerResponses = true
    ): self {
        $config = self::CONFIG;
        if ($localDataMappingConfig !== null) {
            $config['local_data_mapping'] = $localDataMappingConfig;
        }

        $entityManager = TestEntityManager::setUpEntityManager(
            $container,
            DbpRelayBaseOrganizationConnectorCampusonlineExtension::ENTITY_MANAGER_ID
        );

        $eventDispatcher = new EventDispatcher();
        $organizationProvider = new self(
            $entityManager,
            $eventDispatcher
        );

        $organizationProvider->setLogger(new NullLogger());
        $organizationProvider->setConfig($config);
        $organizationProvider->setCampusonlineApiCacheItemPool($campusonlineApiCacheItemPool);

        $organizationEventSubscriber = new OrganizationEventSubscriber($organizationProvider);
        $organizationEventSubscriber->setConfig($config);
        $eventDispatcher->addSubscriber($organizationEventSubscriber);
        foreach ($organizationEventSubscribers as $subscriber) {
            $eventDispatcher->addSubscriber($subscriber);
        }

        $organizationProvider->mockApiResponse($testOrganizationResources, $mockAuthServerResponses);
        $organizationProvider->recreateOrganizationsCache();
        $organizationProvider->reset(); // ensure new api connection is created on subsequent requests

        return $organizationProvider;
    }

    public function mockApiResponse(?array $testOrganizationResources = null, bool $mockAuthServerResponses = true): void
    {
        if ($testOrganizationResources === null) {
            $responses = [
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    file_get_contents(__DIR__.'/public_rest_org_api_response.json')),
            ];
        } else {
            $responseData = [
                'items' => $testOrganizationResources,
            ];
            $responses = [
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    json_encode($responseData)
                ),
            ];
        }

        if ($mockAuthServerResponses) {
            $responses = array_merge(self::createMockAuthServerResponses(), $responses);
        }

        $stack = HandlerStack::create(new MockHandler($responses));
        $this->setClientHandler($stack);
    }

    private static function createMockAuthServerResponses(): array
    {
        return [
            new Response(200, ['Content-Type' => 'application/json;charset=utf-8'], '{"authServerUrl": "https://auth-server.net/"}'),
            new Response(200, ['Content-Type' => 'application/json;charset=utf-8'], '{"token_endpoint": "https://token-endpoint.net/"}'),
            new Response(200, ['Content-Type' => 'application/json;charset=utf-8'], '{"access_token": "token", "expires_in": 3600, "token_type": "Bearer"}'),
        ];
    }
}
