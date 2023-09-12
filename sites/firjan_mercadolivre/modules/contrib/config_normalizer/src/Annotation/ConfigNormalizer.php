<?php

namespace Drupal\config_normalizer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Config normalizer item annotation object.
 *
 * @see \Drupal\config_normalizer\Plugin\ConfigNormalizerManager
 * @see plugin_api
 *
 * @deprecated in config_normalizer:2.0.0-alpha1 and is removed from config_normalizer:2.0.0. No replacement.
 * @see https://www.drupal.org/project/config_normalizer/issues/3230398
 *
 * @Annotation
 */
class ConfigNormalizer extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The weight of the plugin.
   *
   * @var int
   */
  public $weight;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
