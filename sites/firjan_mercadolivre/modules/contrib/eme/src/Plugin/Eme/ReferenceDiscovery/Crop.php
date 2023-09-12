<?php

namespace Drupal\eme\Plugin\Eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eme\ReferenceDiscovery\DirectReferenceDiscoveryPluginBase;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discovery for crop entities associated with files.
 *
 * Crops have to be imported before files, since Crop API creates crop entities
 * on file save. This behavior makes it impossible to "change" crops after a
 * crom migration rollback and reimport.
 *
 * @ReferenceDiscovery(
 *   id = "crop",
 *   provider = "crop"
 * )
 */
class Crop extends DirectReferenceDiscoveryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The crop entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $cropStorage;

  /**
   * Constructs a new Crop reference discovery plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cropStorage = $entity_type_manager->getStorage('crop');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetchReferences(ContentEntityInterface $file): array {
    if (!($file instanceof FileInterface)) {
      return [];
    }

    $results = $this->cropStorage->getQuery()
      ->condition('uri', $file->getFileUri())
      ->accessCheck(FALSE)
      ->execute();

    return array_map(function ($id) {
      return $this->cropStorage->load($id);
    }, $results);
  }

}
