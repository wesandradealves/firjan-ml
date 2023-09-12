<?php

namespace Drupal\eme\ReferenceDiscovery;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface for entity reference discovery plugin manager.
 */
interface DiscoveryPluginManagerInterface extends PluginManagerInterface {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\eme\ReferenceDiscovery\DiscoveryPluginInterface
   *   A fully configured plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = []);

  /**
   * Returns direct reference discovery plugin instances.
   *
   * @return \Drupal\eme\ReferenceDiscovery\DirectReferenceDiscoveryPluginInterface[]
   *   Fully configured plugin instances.
   */
  public function getDirectReferenceDiscoveryPluginInstances();

  /**
   * Returns revers reference discovery plugin instances.
   *
   * @return \Drupal\eme\ReferenceDiscovery\ReverseReferenceDiscoveryPluginInterface[]
   *   Fully configured plugin instances.
   */
  public function getReverseReferenceDiscoveryPluginInstances();

}
