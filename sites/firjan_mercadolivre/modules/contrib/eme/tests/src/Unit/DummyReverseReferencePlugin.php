<?php

namespace Drupal\Tests\eme\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eme\ReferenceDiscovery\ReverseReferenceDiscoveryPluginBase;

/**
 * Dummy reverse reference plugin for testing the DiscoveryPluginManager.
 */
class DummyReverseReferencePlugin extends ReverseReferenceDiscoveryPluginBase {

  /**
   * {@inheritdoc}
   */
  public function fetchReverseReferences(ContentEntityInterface $entity): array {
    return [];
  }

}
