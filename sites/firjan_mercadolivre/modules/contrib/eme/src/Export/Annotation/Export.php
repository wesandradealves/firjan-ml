<?php

namespace Drupal\eme\Export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * EME export plugin annotation.
 *
 * @see \Drupal\eme\Export\ExportPluginManagerInterface
 * @see \Drupal\eme\Export\ExportPluginInterface
 * @see \Drupal\eme\Export\ExportPluginBase
 * @see \Drupal\eme\Plugin\Eme\Export\DrupalInstanceSource
 * @see \Drupal\eme\Plugin\Eme\Export\JsonFileSource
 * @see plugin_api
 *
 * @Annotation
 */
class Export extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * Admin label.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * Description.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
