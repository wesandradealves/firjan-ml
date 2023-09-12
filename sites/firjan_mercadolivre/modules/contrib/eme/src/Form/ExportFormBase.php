<?php

namespace Drupal\eme\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Drupal\eme\Export\ExportPluginManager;
use Drupal\eme\InterfaceAwareExportBatchRunner;
use Drupal\eme\Utility\EmeCollectionUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Base for content export forms.
 */
abstract class ExportFormBase extends FormBase {

  /**
   * List of the discovered modules.
   *
   * @var string[]
   */
  protected $discoveredModules;

  /**
   * Info about discovered previous exports.
   *
   * @var array[]
   */
  protected $discoveredExports;

  /**
   * Export plugin manager.
   *
   * @var \Drupal\eme\Export\ExportPluginManager
   */
  protected $exportPluginManager;

  /**
   * The export batch runner.
   *
   * @var \Drupal\eme\InterfaceAwareExportBatchRunner
   */
  protected $batchRunner;

  /**
   * Construct an export form instance.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\eme\InterfaceAwareExportBatchRunner $batch_runner
   *   The export batch runner.
   * @param \Drupal\eme\Export\ExportPluginManager $export_plugin_manager
   *   The export plugin manager.
   */
  public function __construct(ModuleExtensionList $module_list, InterfaceAwareExportBatchRunner $batch_runner, ExportPluginManager $export_plugin_manager) {
    $this->discoveredModules = array_keys($module_list->reset()->getList());
    $this->discoveredExports = EmeCollectionUtils::getExports($module_list);
    $this->batchRunner = $batch_runner;
    $this->exportPluginManager = $export_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module'),
      $container->get('eme.batch_runner'),
      $container->get('eme.export_plugin_manager')
    );
  }

  /**
   * Batch finish callback.
   */
  public static function finishBatch($success, $results, $operations) {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Content export finished.'), 'status');
      $route_name = $results['redirect']
        ? 'eme.eme_export_download'
        : 'eme.collection';
      return new RedirectResponse(Url::fromRoute($route_name)->toString(), 307);
    }
    else {
      // An error occurred. "$operations" contains the operations which remain
      // "unprocessed".
      $error_operation = reset($operations);
      \Drupal::messenger()->addMessage(t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]), 'error');
    }
  }

}
