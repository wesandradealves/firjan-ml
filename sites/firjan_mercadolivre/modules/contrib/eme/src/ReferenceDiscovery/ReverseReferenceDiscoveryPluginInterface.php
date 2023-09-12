<?php

namespace Drupal\eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface of reverse reference discovery plugins.
 */
interface ReverseReferenceDiscoveryPluginInterface extends DiscoveryPluginInterface {

  /**
   * Returns entities which are related to and depend on the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity which might have reverse references.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The entities which are related to and depend on the given entity.
   */
  public function fetchReverseReferences(ContentEntityInterface $entity): array;

}
