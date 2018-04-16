<?php

namespace A5sys\DoctrineMigrationToolsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 *
 */
class DoctrineMigrationToolsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $locator = new FileLocator(__DIR__.'/../Resources/config/');
        $loader  = new YamlFileLoader($container, $locator);

        $loader->load('services.yml');
    }
}
