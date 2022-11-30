<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationPostEventSubscriber;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const CAMPUS_ONLINE_NODE = 'campus_online';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_base_organization_connector_campusonline');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode(self::CAMPUS_ONLINE_NODE)
                    ->children()
                        ->scalarNode('api_url')->end()
                        ->scalarNode('api_token')->end()
                        ->scalarNode('org_root_id')->end()
                    ->end()
                ->end()
                ->append(OrganizationPostEventSubscriber::getConfigNode())
//                ->arrayNode(self::LOCAL_DATA_MAPPING_NODE)
//                    ->arrayPrototype()
//                        ->children()
//                            ->scalarNode(self::SOURCE_ATTRIBUTE_KEY)->end()
//                            ->scalarNode(self::LOCAL_DATA_ATTRIBUTE_KEY)->end()
//                            ->scalarNode(self::AUTHORIZATION_EXPRESSION_KEY)
//                                ->defaultValue('false')
//                            ->end()
//                        ->end()
//                    ->end()
//                ->end()
            ->end();

        return $treeBuilder;
    }
}
