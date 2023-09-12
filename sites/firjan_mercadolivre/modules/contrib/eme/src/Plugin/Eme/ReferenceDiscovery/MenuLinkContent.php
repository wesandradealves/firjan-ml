<?php

namespace Drupal\eme\Plugin\Eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eme\ReferenceDiscovery\ReverseReferenceDiscoveryPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Menu link content discovery plugin.
 *
 * @ReferenceDiscovery(
 *   id = "menu_link_content",
 *   provider = "menu_link_content"
 * )
 */
class MenuLinkContent extends ReverseReferenceDiscoveryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The menu link storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuLinkContentStorage;

  /**
   * Constructs a new MenuLinkContent reference discovery plugin instance.
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
    $this->menuLinkContentStorage = $entity_type_manager->getStorage('menu_link_content');
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
  public function fetchReverseReferences(ContentEntityInterface $entity): array {
    // Early opt-out.
    if (!$entity->hasLinkTemplate('canonical')) {
      return [];
    }

    // For now, only deal with canonicals.
    // Later we might have something like this:
    // @code
    // $pattern = '{' . $entity->getEntityTypeID() . '}';
    // $entity_related_link_templates = array_filter(
    //   $entity->getEntityType()->getLinkTemplates(),
    //   function (string $link_template) use ($pattern) {
    //     return strpos($link_template, $pattern) !== FALSE;
    //   }
    // );
    // @endcode
    // @see menu_ui_get_menu_link_defaults()
    $entity_type_id = $entity->getEntityTypeID();
    $canonical_url = $entity->toUrl();
    $link_uris_to_search_for = [
      "internal:/{$canonical_url->getInternalPath()}",
      $canonical_url->toUriString(),
      "entity:{$entity_type_id}/{$entity->id()}",
    ];

    $results = $this->menuLinkContentStorage->getQuery()
      ->condition('link.uri', $link_uris_to_search_for, 'IN')
      ->accessCheck(FALSE)
      ->execute();

    return array_map(function ($id) {
      return $this->menuLinkContentStorage->load($id);
    }, $results);
  }

}
