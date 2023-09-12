<?php

namespace Drupal\eme\ReferenceDiscovery;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\eme\ReferenceDiscovery\Annotation\ReferenceDiscovery;
use Psr\Log\LoggerInterface;

/**
 * Manages discovery and instantiation of EME reference discovery plugins.
 */
class DiscoveryPluginManager extends DefaultPluginManager implements DiscoveryPluginManagerInterface {

  /**
   * The cache ID.
   *
   * @const string
   */
  const CACHE_ID = 'eme_reference_discovery_plugins';

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new DiscoveryPluginManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, LoggerInterface $logger) {
    parent::__construct(
      'Plugin/Eme/ReferenceDiscovery',
      $namespaces,
      $module_handler,
      DiscoveryPluginInterface::class,
      ReferenceDiscovery::class
    );

    $this->alterInfo($this->getType());
    $this->setCacheBackend($cache_backend, self::CACHE_ID);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'eme_reference_discovery';
  }

  /**
   * {@inheritdoc}
   */
  protected function handlePluginNotFound($plugin_id, array $configuration) {
    $this->logger->warning('The "%plugin_id" was not found', ['%plugin_id' => $plugin_id]);
    return parent::handlePluginNotFound($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectReferenceDiscoveryPluginInstances() {
    $direct_reference_definitions = array_filter($this->getDefinitions(), function (array $definition) {
      return is_subclass_of($definition['class'], DirectReferenceDiscoveryPluginInterface::class);
    });
    return array_reduce($direct_reference_definitions, function (array $carry, array $definition) {
      $carry[$definition['id']] = $this->createInstance($definition['id']);
      return $carry;
    }, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getReverseReferenceDiscoveryPluginInstances() {
    $indirect_reference_definitions = array_filter($this->getDefinitions(), function (array $definition) {
      return is_subclass_of($definition['class'], ReverseReferenceDiscoveryPluginInterface::class);
    });
    return array_reduce($indirect_reference_definitions, function (array $carry, array $definition) {
      $carry[$definition['id']] = $this->createInstance($definition['id']);
      return $carry;
    }, []);
  }

}
