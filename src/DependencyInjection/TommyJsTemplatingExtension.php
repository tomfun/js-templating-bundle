<?php
namespace Tommy\Bundle\JsTemplatingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Tommy\Bundle\JsTemplatingBundle\DependencyInjection\Compiler\JsmodelProviderPass;
use Tommy\Bundle\JsTemplatingBundle\Service\Util;

/**
 * Bundle setup.
 *
 * @author Kevin Montag <kevin@hearsay.it>
 */
class TommyJsTemplatingExtension extends Extension
{

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration($this->getAlias());
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yml');
        $loader->load('twigjs.yml');

        $container->setParameter($this->getAlias() . '.' . 'auto_dump', $config['auto_dump']);
        $container->setParameter($this->getAlias() . '.' . 'use_symlinks', $config['use_symlinks']);
        $container->setParameter($this->getAlias() . '.' . 'json_file', $config['json_file']);
        $globalLibs = [];
        foreach ($config['base_libs'] as $type => $path) {
            $globalLibs[] = [
                'type'       => $type,
                'path'       => Util::getRealPath($path, $container),
                'exportName' => '',
            ];
        }
        if (count($globalLibs)) {
            $container->setParameter(
                $this->getAlias() . '.' . JsmodelProviderPass::JS_CONFIG_POSTFIX,
                $globalLibs
            );
        }

        $container->setParameter(
            $this->getAlias(),
            $config
        );
        $container->setParameter(
            $this->getAlias() . '.base_dir',
            $config['base_dir']
        );

        $configurationBuilder = $container->getDefinition(
            $this->getAlias() . '.configuration_builder'
        );

        foreach ($config['options'] as $option => $settings) {
            $configurationBuilder->addMethodCall('addOption', [$option, $settings['value']]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        // Speedup
        return 'tommy_js_templating';
    }
}
