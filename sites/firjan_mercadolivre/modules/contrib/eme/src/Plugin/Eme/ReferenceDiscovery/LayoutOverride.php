<?php

namespace Drupal\eme\Plugin\Eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eme\ReferenceDiscovery\DirectReferenceDiscoveryPluginBase;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\Section;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discovery plugin for entities used in layout override field values.
 *
 * @ReferenceDiscovery(
 *   id = "layout_override",
 *   provider = "layout_builder"
 * )
 */
class LayoutOverride extends DirectReferenceDiscoveryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The block content entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|null
   */
  protected $blockContentStorage;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new LayoutOverride reference discovery plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->blockContentStorage = $entity_type_manager->hasDefinition('block_content')
      ? $entity_type_manager->getStorage('block_content')
      : NULL;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetchReferences(ContentEntityInterface $entity): array {
    if (!$this->blockContentStorage) {
      return [];
    }

    $layout_fields = array_filter($entity->getFields(FALSE), function (FieldItemListInterface $field) {
      return $field instanceof LayoutSectionItemList;
    });

    return array_reduce($layout_fields, function (array $carry, LayoutSectionItemList $field) {
      foreach ($field->getSections() as $section) {
        assert($section instanceof Section);
        foreach ($section->getComponents() as $section_component) {
          $config = (array) $section_component->get('configuration');

          switch ($config['provider'] ?? NULL) {
            case 'block_content':
              // This section component is a reusable block content entity.
              $carry[] = $this->entityRepository->loadEntityByUuid('block_content', preg_replace('/^block_content:/', '', $config['id']));
              break;

            case 'layout_builder':
              if (strpos($config['id'], 'inline_block:') === 0) {
                // This section component is an inline (non-reusable) block
                // content entity.
                $carry[] = $this->blockContentStorage->loadRevision((int) $config['block_revision_id']);
              }
              break;
          }
        }
      }
      return $carry;
    }, []);
  }

}
