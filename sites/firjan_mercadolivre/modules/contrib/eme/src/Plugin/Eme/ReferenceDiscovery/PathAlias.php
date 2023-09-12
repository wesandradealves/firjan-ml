<?php

namespace Drupal\eme\Plugin\Eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eme\ReferenceDiscovery\DirectReferenceDiscoveryPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Path alias discovery plugin.
 *
 * Logically, path aliases should be 'reverse' references: when their
 * destination entity is missing, there is no need for them (imho).
 *
 * But if Pathauto is installed, and the related entity has pattern, then the
 * alias has to be migrated before the related content entity being migrated,
 * otherwise pathauto will create an alias.
 *
 * @ReferenceDiscovery(
 *   id = "path_alias",
 *   provider = "path_alias"
 * )
 */
class PathAlias extends DirectReferenceDiscoveryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The path alias storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $pathAliasStorage;

  /**
   * Constructs a new PathAlias reference discovery plugin instance.
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
    $this->pathAliasStorage = $entity_type_manager->getStorage('path_alias');
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
  public function fetchReferences(ContentEntityInterface $entity): array {
    // Early opt-out.
    if (!$entity->hasLinkTemplate('canonical')) {
      return [];
    }

    // @see Drupal\path\Plugin\Field\FieldWidget\PathWidget.
    $entity_internal_path = '/' . $entity->toUrl()->getInternalPath();
    $results = $this->pathAliasStorage->getQuery()
      ->condition('path', $entity_internal_path)
      ->accessCheck(FALSE)
      ->execute();

    return array_map(function ($id) {
      return $this->pathAliasStorage->load($id);
    }, $results);
  }

}
