<?php

namespace Drupal\Tests\eme\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\eme\ReferenceDiscovery\ReverseReferenceDiscoveryPluginInterface;

/**
 * Dummy direct and reverse reference plugin.
 */
class DummyAllReferencePlugin extends DummyDirectReferencePlugin implements ReverseReferenceDiscoveryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function fetchReferences(ContentEntityInterface $entity): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function fetchReverseReferences(ContentEntityInterface $entity): array {
    return [];
  }

}
