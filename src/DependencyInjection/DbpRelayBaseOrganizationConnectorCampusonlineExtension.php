<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Cron\CacheRefreshCronJob;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationEventSubscriber;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\Doctrine\DoctrineConfiguration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayBaseOrganizationConnectorCampusonlineExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    public const ENTITY_MANAGER_ID = 'dbp_relay_base_organization_connector_campusonline_bundle';

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $container->getDefinition(OrganizationProvider::class)
            ->addMethodCall('setConfig', [$mergedConfig]);

        $container->getDefinition(OrganizationEventSubscriber::class)
            ->addMethodCall('setConfig', [$mergedConfig]);

        $container->getDefinition(CacheRefreshCronJob::class)
            ->addMethodCall('setConfig', [$mergedConfig]);
    }

    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);

        DoctrineConfiguration::prependEntityManagerConfig($container, self::ENTITY_MANAGER_ID,
            $config[Configuration::DATABASE_URL],
            __DIR__.'/../Entity',
            'Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity');
        DoctrineConfiguration::prependMigrationsConfig($container,
            __DIR__.'/../Migrations',
            'Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Migrations');
    }
}
