<?php
namespace Tommy\Bundle\JsTemplatingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelInterface;
use Tommy\Bundle\JsTemplatingBundle\Service\Jsmodel\JsmodelProviderInterface;
use Tommy\Bundle\JsTemplatingBundle\Service\Util;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * JsmodelProviderPass.
 *
 * @author Bogdan Yurov <bogdan@yurov.me>
 */
class JsmodelProviderPass implements
    CompilerPassInterface
{
    const CLASS_TAG = 'werkint.requirejs.jsmodelprovider';
    const EXT_NAME = 'tommy_js_templating';
    const JS_MODEL_POSTFIX = 'jsmodeldir';
    const JS_EXPORT_NAME_POSTFIX = 'jsmodel.name';
    const JS_CONFIG_POSTFIX = 'tommy_js_config';

    private $kernel;

    /**
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }


    /**
     * {@inheritdoc}
     */
    public function process(
        ContainerBuilder $container
    ) {
        $config = $container->getParameter(static::EXT_NAME);
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle->getContainerExtension()) {
                $alias = strtolower(preg_replace('/([a-z])([A-Z]+)/', '$1-$2', $bundle->getName()));
                $alias = preg_replace('/^(.*?)-?bundle$/', '$1', $alias);
            } else {
                $alias = $bundle->getContainerExtension()->getAlias();
            }
            $configPath = $alias . '.' . static::JS_CONFIG_POSTFIX;
            if ($container->hasParameter($configPath) && $bundleConfig = $container->getParameter($configPath)) {
                if (is_array($bundleConfig) && count($bundleConfig) === 3 && isset($bundleConfig['path'])) {
                    $this->addNamespaceMappingFromConfig($bundleConfig, $container, $bundle);
                    continue;
                }
                foreach ($bundleConfig as $item) {
                    $this->addNamespaceMappingFromConfig($item, $container, $bundle);
                }
                continue;
            }
            //legacy, simple configuration for bundle
            $configPath = $alias . '.' . static::JS_MODEL_POSTFIX;
            if (!$container->hasParameter($configPath) || !($dir = $container->getParameter($configPath))) {
                $dir = $bundle->getPath() . '/Resources/scripts/jsmodel';
            }
            if ($dir = realpath($dir)) {
                $configPath = $alias . '.' . static::JS_EXPORT_NAME_POSTFIX;
                if (!$container->hasParameter($configPath) || !($name = $container->getParameter($configPath))) {
                    $name = str_replace('_', '-', $alias);
                }
                $this->addNamespaceMapping($dir, $name, 'js', $container, true);
            }
        }
        //extra legacy
        $list = $container->findTaggedServiceIds(static::CLASS_TAG);
        foreach ($list as $id => $attributes) {
            $srv = $container->get($id);
            if (!($srv instanceof JsmodelProviderInterface)) {
                throw new \Exception('JsmodelProviderInterface interface is missing');
            }
            $paths = $srv->getPaths();

            foreach ($paths as $path) {
                $location = is_array($path) ? $path[0] : $path;
                $path = is_array($path) ? $path[1] : '';

                $this->addNamespaceMapping($location, $path, 'js', $container, true);
            }
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param BundleInterface  $bundle
     * @throws \Exception
     */
    protected function addNamespaceMappingFromConfig(array $config, ContainerBuilder $container, BundleInterface $bundle)
    {
        if (!(isset($config['path']) && isset($config['exportName']) && isset($config['type']))) {
            throw new \Exception('JsTemplating exception: path, exportName or type is missing for bundle config');
        }
        $path = $config['path'];
        if (substr($path, 0, 1) !== '/') {
            $path = $bundle->getPath() . '/' . $path;
        }
        if (realpath($path)) {
            $this->addNamespaceMapping($path, $config['exportName'], $config['type'], $container, true);
        }
    }

    /**
     * Configure a mapping from a filesystem path to a RequireJS namespace.
     *
     * @param string           $location
     * @param string           $path
     * @param                  $type
     * @param ContainerBuilder $container
     * @param boolean          $generateAssets
     */
    protected function addNamespaceMapping(
        $location,
        $path,
        $type,
        ContainerBuilder $container,
        $generateAssets = true
    ) {
        $location = Util::getRealPath($location, $container);

        // Register the namespace with the configuration
        $mapping = $container->getDefinition(static::EXT_NAME . '.namespace_mapping');
        $mapping->addMethodCall('registerNamespace', [$path, $location, $type]);

        $config = $container->getDefinition(static::EXT_NAME . '.configuration_builder');
        $config->addMethodCall('setPath', [$path, $location, $type]);

//        if ($path && $container->hasDefinition(static::EXT_NAME . '.optimizer_filter')) {
//            $filter = $container->getDefinition(static::EXT_NAME . '.optimizer_filter');
//            $filter->addMethodCall('addPath', [$path, preg_replace('~\.js$~', '', $location)]);
//        }

//        if ($generateAssets) {
//            $resource = new DefinitionDecorator(static::EXT_NAME . '.filenames_resource');
//            $resource->setArguments([$location]);
//            $resource->addTag('assetic.formula_resource', ['loader' => 'require_js']);
//            $container->addDefinitions([
//                static::EXT_NAME . '.filenames_resource.' . md5($location) => $resource,
//            ]);
//        }
    }
}
