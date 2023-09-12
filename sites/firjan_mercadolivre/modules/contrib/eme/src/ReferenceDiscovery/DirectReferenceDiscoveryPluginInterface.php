<?php

namespace Drupal\eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface of direct reference discovery plugins.
 */
interface DirectReferenceDiscoveryPluginInterface extends DiscoveryPluginInterface {

  /**
   * Returns content entities which are referenced by the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to check for references.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The directly referenced content entities.
   */
  public function fetchReferences(ContentEntityInterface $entity): array;

}
