<?php

namespace Drupal\config_normalizer;

use Drupal\Core\Config\Schema\Ignore;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\Schema\Sequence;
use Drupal\Core\Config\Schema\SequenceDataDefinition;
use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Config\UnsupportedDataTypeConfigException;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Type\FloatInterface;
use Drupal\Core\TypedData\Type\IntegerInterface;

/**
 * Class responsible for performing configuration normalization.
 */
class ConfigNormalizer implements ConfigNormalizerInterface {

  /**
   * The typed config manager to get the schema from.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * ConfigCaster constructor.
   *
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager to look up the schema.
   */
  public function __construct(TypedConfigManagerInterface $typedConfigManager) {
    $this->typedConfigManager = $typedConfigManager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($name, array $data) {
    // The sorter is an anonymous class extending from StorableConfigBase.
    // We need to do this because the logic for sorting is in a class meant
    // for config objects and not for services.
    $sorter = new class($this->typedConfigManager) extends StorableConfigBase {

      /**
       * Sort the config.
       *
       * This method is named to make it unlikely that it is overriding a core
       * method.
       *
       * @param string $name
       *   The config name.
       * @param array $data
       *   The data.
       *
       * @return array
       *   The sorted array.
       */
      public function anonymousSort(string $name, array $data): array {
        // Set the object up.
        self::validateName($name);
        $this->validateKeys($data);
        $this->setName($name)->initWithData($data);

        // This is essentially what \Drupal\Core\Config\Config::save does when
        // there is untrusted data before persisting it and dispatching events.
        if ($this->typedConfigManager->hasConfigSchema($this->name)) {
          // We use the patched version of the method.
          $this->data = $this->castValue2852557(NULL, $this->data);
        }
        else {
          foreach ($this->data as $key => $value) {
            $this->validateValue($key, $value);
          }
        }

        // That should be it.
        return $this->data;
      }

      /**
       * Casts the value to correct data type using the configuration schema.
       *
       * This is the patched version from
       * https://www.drupal.org/project/drupal/issues/2852557
       *
       * @param string|null $key
       *   A string that maps to a key within the configuration data. If NULL
       *   the top level mapping will be processed.
       * @param mixed $value
       *   Value to associate with the key.
       *
       * @return mixed
       *   The value cast to the type indicated in the schema.
       *
       * @throws \Drupal\Core\Config\UnsupportedDataTypeConfigException
       *   If the value is unsupported in configuration.
       */
      protected function castValue2852557($key, $value) {
        $element = $this->getSchemaWrapper();
        if ($key !== NULL) {
          $element = $element->get($key);
        }

        // Do not cast value if it is unknown or defined to be ignored.
        if ($element && ($element instanceof Undefined || $element instanceof Ignore)) {
          $this->validateValue($key, $value);
          return $value;
        }
        if (is_scalar($value) || $value === NULL) {
          if ($element && $element instanceof PrimitiveInterface) {
            $empty_value = $value === '' && ($element instanceof IntegerInterface || $element instanceof FloatInterface);

            if ($value === NULL || $empty_value) {
              $value = NULL;
            }
            else {
              $value = $element->getCastedValue();
            }
          }
        }
        else {
          // Throw exception on any non-scalar or non-array value.
          if (!is_array($value)) {
            throw new UnsupportedDataTypeConfigException("Invalid data type for config element {$this->getName()}:$key");
          }
          // Recurse into any nested keys.
          foreach ($value as $nested_value_key => $nested_value) {
            $lookup_key = $key ? $key . '.' . $nested_value_key : $nested_value_key;
            $value[$nested_value_key] = $this->castValue2852557($lookup_key, $nested_value);
          }

          // Only sort maps when we have more than 1 element to sort.
          if ($element instanceof Mapping && count($value) > 1) {
            $mapping = $element->getDataDefinition()['mapping'];
            if (is_array($mapping)) {
              // Only sort the keys in $value.
              $mapping = array_intersect_key($mapping, $value);
              // Sort the array in $value using the mapping definition.
              $value = array_replace($mapping, $value);
            }
          }

          if ($element instanceof Sequence) {
            $data_definition = $element->getDataDefinition();
            if ($data_definition instanceof SequenceDataDefinition) {
              // Apply any sorting defined on the schema.
              switch ($data_definition->getOrderBy()) {
                case 'key':
                  ksort($value);
                  break;

                case 'value':
                  // The PHP documentation notes that "Be careful when sorting
                  // arrays with mixed types values because sort() can produce
                  // unpredictable results". There is no risk here because
                  // \Drupal\Core\Config\StorableConfigBase::castValue() has
                  // already cast all values to the same type using the
                  // configuration schema.
                  sort($value);
                  break;

              }
            }
          }
        }
        return $value;
      }

      /**
       * The constructor for passing the TypedConfigManager.
       *
       * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
       *   The taped config manager.
       */
      public function __construct(TypedConfigManagerInterface $typedConfigManager) {
        $this->typedConfigManager = $typedConfigManager;
      }

      /**
       * {@inheritdoc}
       */
      public function save($has_trusted_data = FALSE) {
        throw new \LogicException();
      }

      /**
       * {@inheritdoc}
       */
      public function delete() {
        throw new \LogicException();
      }

    };

    // Sort the data using the core class we extended.
    return $sorter->anonymousSort($name, $data);
  }

}
