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

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

class LiipViewExtension extends Extension
{
    /**
     * Xml config files to load
     * @var array
     */
    protected $resources = array(
        'config' => 'view.xml',
    );

    /**
     * Loads the services based on your application configuration.
     *
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        // TODO move this to the Configuration class as soon as it supports setting such a default
        array_unshift($configs, array(
            'formats' => array(
                'json' => 'liip_view.encoder.json',
                'xml' => 'liip_view.encoder.xml',
                'html' => 'liip_view.encoder.html',
            )
        ));

        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->process($configuration->getConfigTree(), $configs);

        $loader = $this->getFileLoader($container);
        $loader->load($this->resources['config']);

        foreach ($config['class'] as $key => $value) {
            $container->setParameter($this->getAlias().'.'.$key.'.class', $value);
        }

        $container->setParameter($this->getAlias().'.formats', $config['formats']);
    }

    /**
     * Get File Loader
     *
     * @param ContainerBuilder $container
     */
    public function getFileLoader($container)
    {
        return new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
    }

    /**
     * @inheritDoc
     */
    public function getAlias()
    {
        return 'liip_view';
    }
}
