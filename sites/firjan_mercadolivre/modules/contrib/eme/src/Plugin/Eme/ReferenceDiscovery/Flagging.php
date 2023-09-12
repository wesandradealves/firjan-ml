<?php

namespace Drupal\eme\Plugin\Eme\ReferenceDiscovery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eme\ReferenceDiscovery\ReverseReferenceDiscoveryPluginBase;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Flagging discovery plugin.
 *
 * @ReferenceDiscovery(
 *   id = "flagging",
 *   provider = "flag"
 * )
 */
class Flagging extends ReverseReferenceDiscoveryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The flag(ging) manager.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagManager;

  /**
   * Constructs a new MenuLinkContent reference discovery plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\flag\FlagServiceInterface $flag_manager
   *   The flag(ging) manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagServiceInterface $flag_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagManager = $flag_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetchReverseReferences(ContentEntityInterface $entity): array {
    return $this->flagManager->getAllEntityFlaggings($entity);
  }

}
