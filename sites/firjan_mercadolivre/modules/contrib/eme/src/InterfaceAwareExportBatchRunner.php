<?php

declare(strict_types=1);

namespace Drupal\eme;

use Drupal\eme\Export\ExportPluginInterface;

/**
 * An interface aware batch runner service.
 */
final class InterfaceAwareExportBatchRunner {

  /**
   * Sets up an export batch.
   *
   * @param \Drupal\eme\Export\ExportPluginInterface $export_plugin
   *   The EME export plugin instance to use.
   * @param string|string[]|null $finish_callback
   *   Finish callback of the export batch.
   */
  public function setupBatch(ExportPluginInterface $export_plugin, $finish_callback = NULL): void {
    if ($export_plugin->alreadyProcessing()) {
      throw new ExportException("An another export process may be exporting content. If this is not true, then empty the 'eme' record from the 'semaphore' table and try again.");
    }

    try {
      $batch = [
        'title' => $this->translate('Export content to migration'),
        'init_message' => $this->translate('Starting content reference discovery.'),
        'progress_message' => $this->translate('Completed step @current of @total.'),
        'error_message' => $this->translate('Content export has encountered an error.'),
      ];
      foreach ($export_plugin->tasks() as $process_step) {
        $batch['operations'][] = [
          [$export_plugin, 'executeExportTask'],
          [$process_step],
        ];
      }

      if ($finish_callback) {
        $batch['finished'] = $finish_callback;
      }
    }
    catch (\Exception $e) {
      throw new ExportException('Cannot initialize the export process.', 0, $e);
    }

    batch_set($batch);
  }

  /**
   * Translates a message.
   *
   * @param string $message
   *   The message to translate.
   * @param array|null $args
   *   Arguments of the message.
   * @param array|null $context
   *   Context of the message.
   *
   * @return mixed
   *   The translated message.
   */
  public function translate(string $message, array $args = [], array $context = []) {
    $callback = function_exists('t') ? 't' : NULL;
    if (!$callback) {
      $callback = function_exists('dt') ? 'dt' : NULL;
    }
    if ($callback) {
      return call_user_func_array(
        $callback,
        [
          $message,
          $args,
          $context,
        ]
      );
    }

    return $message;
  }

}
