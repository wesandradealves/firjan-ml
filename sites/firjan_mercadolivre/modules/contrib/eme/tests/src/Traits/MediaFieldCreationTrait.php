<?php

namespace Drupal\Tests\eme\Traits;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Methods for creating media reference fields.
 */
trait MediaFieldCreationTrait {

  /**
   * Creates a new media field.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle that this field will be added to.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   *
   * @return \Drupal\field\FieldStorageConfigInterface
   *   The media field.
   */
  public function createMediaField($name, $entity_type, $bundle, array $storage_settings = [], array $field_settings = [], array $widget_settings = []) {
    $storage_settings = ['target_type' => 'media'] + $storage_settings;
    $field_storage = FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $name,
      'type' => 'entity_reference',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ]);
    $field_storage->save();

    $this->attachMediaField($name, $entity_type, $bundle, $field_settings, $widget_settings);
    return $field_storage;
  }

  /**
   * Attaches a media field to an entity.
   *
   * @param string $name
   *   The name of the new field (all lowercase), exclude the "field_" prefix.
   * @param string $entity_type
   *   The entity type this field will be added to.
   * @param string $bundle
   *   The bundle this field will be added to.
   * @param array $field_settings
   *   A list of field settings that will be added to the defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   */
  public function attachMediaField($name, $entity_type, $bundle, array $field_settings = [], array $widget_settings = []) {
    $field = [
      'field_name' => $name,
      'label' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ];
    FieldConfig::create($field)->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay($entity_type, $bundle)
      ->setComponent($name, [
        'type' => 'entity_reference_autocomplete',
        'settings' => $widget_settings,
      ])
      ->save();
    // Assign display settings.
    \Drupal::service('entity_display.repository')
      ->getViewDisplay($entity_type, $bundle)
      ->setComponent($name, [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
      ])
      ->save();
  }

}
