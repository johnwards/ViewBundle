<?php

/*
 * This file is part of the Liip/ViewBundle
 *
 * (c) Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Bundle\Liip\ViewBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ViewExtension extends Extension
{
    /**
     * Yaml config files to load
     * @var array
     */
    protected $resources = array(
        'config' => 'config.xml',
    );

    /**
     * Loads the services based on your application configuration.
     *
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function configLoad($config, ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->getAlias())) {
            $loader = $this->getFileLoader($container);
            $loader->load($this->resources['config']);
        }

        foreach ($config as $key => $value) {
            $container->setParameter($this->getAlias().'.'.$key, $value);
        }
    }

    /**
     * Get File Loader
     *
     * @param ContainerBuilder $container
     */
    public function getFileLoader($container)
    {
        return new YamlFileLoader($container, __DIR__.'/../Resources/config');
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
