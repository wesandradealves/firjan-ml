<?php

namespace Drupal\eme\Export;

use Drupal\Component\Assertion\Inspector;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\eme\Export\Annotation\Export;
use Psr\Log\LoggerInterface;

/**
 * Manages discovery and instantiation of EME export type plugins.
 */
class ExportPluginManager extends DefaultPluginManager implements ExportPluginManagerInterface {

  /**
   * The cache ID.
   *
   * @const string
   */
  const CACHE_ID = 'eme_source_plugins';

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new EmeSourcePluginManager instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, LoggerInterface $logger) {
    parent::__construct(
      'Plugin/Eme/Export',
      $namespaces,
      $module_handler,
      ExportPluginInterface::class,
      Export::class
    );

    $this->alterInfo($this->getType() . '_info');
    $this->setCacheBackend($cache_backend, self::CACHE_ID);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  protected function getType() {
    return 'eme_export_type';
  }

  /**
   * {@inheritdoc}
   */
  protected function handlePluginNotFound($plugin_id, array $configuration) {
    $this->logger->warning('The "%plugin_id" was not found', ['%plugin_id' => $plugin_id]);
    return parent::handlePluginNotFound($plugin_id, $configuration);
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\eme\Export\ExportPluginInterface
   *   A fully configured plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, ContainerFactoryPluginInterface::class)) {
      // @codingStandardsIgnoreLine
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition);
    }
    assert($plugin instanceof ExportPluginInterface);

    if (!empty($violations = $this->getExportPluginViolations($plugin))) {
      $main_message = sprintf("The export plugin with ID '%s' cannot be instantiated because the plugin class '%s' is invalid: \n - ", $plugin_id, $plugin_class);
      throw new PluginException($main_message . implode("\n - ", $violations));
    }

    return $plugin;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\eme\Export\ExportPluginInterface|null
   *   A fully configured plugin instance.
   */
  public function getInstance(array $options) {
    $plugin = parent::getInstance($options);
    if ($plugin instanceof ExportPluginInterface) {
      return $plugin;
    }

    return NULL;
  }

  /**
   * Determines whether the given export plugin is valid or not.
   *
   * @param \Drupal\eme\Export\ExportPluginInterface $plugin
   *   The export plugin to validate.
   *
   * @return string[]
   *   The violations.
   */
  protected function getExportPluginViolations(ExportPluginInterface $plugin): array {
    $task_violations = [];
    $task_param_violations = [];
    $task_return_violations = [];

    // The plugin should have at least one task.
    if (count($tasks = $plugin->tasks()) < 1) {
      return ['There are no tasks defined.'];
    }

    // Every task has to be a string.
    if (!Inspector::assertAllStrings($tasks)) {
      $task_violations[] = 'Tasks should be the method names of the export plugin and must be strings.';
    }
    // The class exists, since we use an export plugin instance. This won't ever
    // throw a ReflectionException.
    $reflection = new \ReflectionClass($plugin);

    foreach ($tasks as $task) {
      try {
        $task_method = $reflection->getMethod($task);
        if (!$task_method->isProtected()) {
          $task_violations[] = sprintf(
            "The plugin defines a non-protected task method: '%s'. Export tasks should be defined as protected.",
            $task
          );
        }
      }
      catch (\ReflectionException $e) {
        $task_violations[] = sprintf(
          "The plugin tries to use a missing export task method: '%s'.",
          $task
        );
        // Missing method, so we cannot check the method parameters.
        continue;
      }

      // Validate parameters.
      $task_param_violations = array_merge(
        $task_param_violations,
        self::validateExportTaskParameter($task_method)
      );

      // Validate return type.
      $task_return_violations = array_merge(
        $task_return_violations,
        self::validateExportTaskReturn($task_method)
      );
    }

    return array_merge($task_violations, $task_param_violations, $task_return_violations);
  }

  /**
   * Returns the parameter related criteria violations of an export task.
   *
   * @param \ReflectionMethod $task_method
   *   The export task (reflection) method.
   *
   * @return string[]
   *   The violations.
   */
  protected static function validateExportTaskParameter(\ReflectionMethod $task_method): array {
    $task_param_violations = [];

    // Every task should have a "$context".
    if (count($task_method_params = $task_method->getParameters()) < 1) {
      $task_param_violations[] = sprintf(
        "The task method '%s' is missing the required batch context parameter.",
        $task_method->getName()
      );
    }

    $context_param = reset($task_method_params);
    $context_param_name = $context_param->getName();

    // The context parameter should be passed by reference and should be
    // type-hinted as \ArrayAccess.
    if (!$context_param->isPassedByReference()) {
      $task_param_violations[] = sprintf(
        "The batch context parameter '\$%s' of task method '%s' isn't passed by reference.",
        $context_param_name,
        $task_method->getName()
      );
    }

    if (!$context_param->hasType()) {
      $task_param_violations[] = sprintf(
        "The batch context parameter '$%s' of task method '%s' is missing the '\ArrayAccess' type hint.",
        $context_param_name,
        $task_method->getName()
      );
    }
    elseif ($context_param->getType()->getName() !== \ArrayAccess::class) {
      $task_param_violations[] = sprintf("The batch context parameter '$%s' of task method '%s' has wrong type hint: '%s'. It should be typehinted as '\ArrayAccess'.",
        $context_param_name,
        $task_method->getName(),
        $context_param->getType()->getName()
      );
    }

    return $task_param_violations;
  }

  /**
   * Returns the return type related criteria violations of an export task.
   *
   * @param \ReflectionMethod $task_method
   *   The export task (reflection) method.
   *
   * @return string[]
   *   The violations.
   */
  protected static function validateExportTaskReturn(\ReflectionMethod $task_method): array {
    if (!$task_method->hasReturnType()) {
      return [
        sprintf(
          "The return type of task method '%s' is missing. Export tasks should have a 'void' return type.",
          $task_method->getName()
        ),
      ];
    }

    if (($task_method_return = $task_method->getReturnType()->getName()) !== 'void') {
      return [
        sprintf(
        "The return type of task method '%s' is '%s'. Export tasks should have a 'void' return type.",
          $task_method->getName(),
          $task_method_return
        ),
      ];
    }

    return [];
  }

}
