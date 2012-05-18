<?php

/**
 * This file is part of the RollerworksDBBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\Bundle\DBBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This class contains the configuration information for the bundle
 *
 * This information is solely responsible for how the different configuration
 * sections are normalized, and merged.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode    = $treeBuilder->root('rollerworks_db');

        $rootNode
            ->children()
                ->arrayNode('user_exception_listener')
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('check_class_in')
                    ->children()

                        ->arrayNode('check_class_in')
                            ->addDefaultsIfNotSet()
                            ->defaultValue(array('PDOException', 'Doctrine\DBAL\Driver\OCI8\OCI8Exception'))
                            ->prototype('scalar')->end()
                        ->end()

                        ->scalarNode('check_prefix')->defaultValue('app-exception: ')->end()
                    ->end()
                ->end()
            ->end()
        ->end() ;

        return $treeBuilder;
    }
}
