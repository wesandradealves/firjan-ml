<?php

namespace Drupal\Tests\eme\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eme\ReferenceDiscovery\DirectReferenceDiscoveryPluginBase;

/**
 * Dummy direct reference plugin for testing the DiscoveryPluginManager.
 */
class DummyDirectReferencePlugin extends DirectReferenceDiscoveryPluginBase {

  /**
   * {@inheritdoc}
   */
  public function fetchReferences(ContentEntityInterface $entity): array {
    return [];
  }

}
