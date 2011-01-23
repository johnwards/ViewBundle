<?php

/*
 * This file is part of the Liip/ViewBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Liip\ViewBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ViewExtension extends Extension
{
    /**
     * Loads the services based on your application configuration.
     *
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        // TODO: merge configs
        $config = reset($config);

        if (!$container->hasDefinition('view')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            $loader->load('config.xml');
        }
    }

    /**
     * @inheritDoc
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * @inheritDoc
     */
    public function getNamespace()
    {
        return 'http://liip.ch/schema/dic/view';
    }

    /**
     * @inheritDoc
     */
    public function getAlias()
    {
        return 'view';
    }
}
