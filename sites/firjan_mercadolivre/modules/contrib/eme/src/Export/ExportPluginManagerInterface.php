<?php

namespace Drupal\eme\Export;

/**
 * Interface for export plugin manager.
 */
interface ExportPluginManagerInterface {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\eme\Export\ExportPluginInterface
   *   A fully configured plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = []);

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\eme\Export\ExportPluginInterface|null
   *   A fully configured plugin instance.
   */
  public function getInstance(array $options);

}
