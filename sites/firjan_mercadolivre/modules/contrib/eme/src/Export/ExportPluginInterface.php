<?php

namespace Drupal\eme\Export;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Psr\Log\LoggerInterface;

/**
 * Interface of export plugins.
 */
interface ExportPluginInterface extends PluginInspectionInterface {

  /**
   * The ordered tasks of the export plugin to execute.
   *
   * These tasks should be defined as public methods inside the class, and their
   * only parameter has to be a reference. The type can be either 'array' or
   * '\ArrayAccess'.
   *
   * @return string[]
   *   The ordered tasks of the export plugin to execute.
   */
  public function tasks(): array;

  /**
   * Calls a step and catches exceptions.
   *
   * @param string $export_task
   *   The step (a method of the export plugin) to call.
   * @param array|\ArrayAccess $context
   *   A batch context array or a DrushBatchContext object. If the step does not
   *   need a batch, then the only array key that is used is
   *   $context['finished']. A process have to set $context['finished'] = 1 when
   *   it is done.
   *
   * @throws \Drupal\eme\ExportException
   *   If the given step cannot be called or throws an exception.
   */
  public function executeExportTask(string $export_task, &$context): void;

  /**
   * Sets the logger.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger to use.
   */
  public function setLogger(LoggerInterface $logger = NULL): void;

  /**
   * Checks whether the export plugin instance has a logger.
   *
   * @return bool
   *   TRUE if the export plugin instance has a logger, FALSE otherwise.
   */
  public function hasLogger(): bool;

}
