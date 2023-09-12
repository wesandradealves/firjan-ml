<?php

namespace Drupal\eme\ReferenceDiscovery\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * EME reference discovery plugin annotation.
 *
 * @see \Drupal\eme\ReferenceDiscovery\DiscoveryPluginManagerInterface
 * @see \Drupal\eme\ReferenceDiscovery\DirectReferenceDiscoveryPluginInterface
 * @see \Drupal\eme\ReferenceDiscovery\ReverseReferenceDiscoveryPluginInterface
 * @see plugin_api
 *
 * @Annotation
 */
class ReferenceDiscovery extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

}
