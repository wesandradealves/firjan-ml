<?php

namespace Drupal\eme\Plugin\Eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\eme\ReferenceDiscovery\DirectReferenceDiscoveryPluginBase;

/**
 * Entity reference discovery plugin for entity reference based fields.
 *
 * @ReferenceDiscovery(
 *   id = "entity_reference"
 * )
 */
class EntityReference extends DirectReferenceDiscoveryPluginBase {

  /**
   * {@inheritdoc}
   */
  public function fetchReferences(ContentEntityInterface $entity): array {
    $reference_fields = array_filter($entity->getFields(FALSE), function (FieldItemListInterface $field) {
      return $field instanceof EntityReferenceFieldItemListInterface;
    });
    return array_reduce($reference_fields, function (array $carry, EntityReferenceFieldItemListInterface $field) {
      foreach ($field->referencedEntities() as $referenced_entity) {
        if ($referenced_entity instanceof ContentEntityInterface) {
          $carry[] = $referenced_entity;
        }
      }
      return $carry;
    }, []);
  }

}
