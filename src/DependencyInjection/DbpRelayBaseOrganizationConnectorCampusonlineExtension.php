<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationPostEventSubscriber;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayBaseOrganizationConnectorCampusonlineExtension extends ConfigurableExtension
{
    public function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $organizationCache = $container->register('dbp_api.cache.organization.campus_online', FilesystemAdapter::class);
        $organizationCache->setArguments(['relay-base-organization-connector-campusonline', 60, '%kernel.cache_dir%/dbp/relay-base-organization-connector-campusonline']);
        $organizationCache->setPublic(true);
        $organizationCache->addTag('cache.pool');

        $courseApi = $container->getDefinition(OrganizationApi::class);
        $courseApi->addMethodCall('setCache', [$organizationCache, 3600]);
        $courseApi->addMethodCall('setConfig', [$mergedConfig[Configuration::CAMPUS_ONLINE_NODE] ?? []]);

        $courseApi = $container->getDefinition(OrganizationPostEventSubscriber::class);
        $courseApi->addMethodCall('setConfig', [$mergedConfig]);
    }
}
