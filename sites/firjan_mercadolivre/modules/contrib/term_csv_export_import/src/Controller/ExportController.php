<?php

namespace Drupal\term_csv_export_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\TermStorage;

/**
 * Class ExportController.
 */
class ExportController extends ControllerBase {

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected  $vocabulary;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * Export Variable.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  public $export;

  /**
   * {@inheritdoc}
   */
  public function __construct(TermStorage $term_storage, $vocabulary) {
    $this->termStorage = $term_storage;
    $this->vocabulary = $vocabulary;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($include_ids, $include_headers, $include_fields) {
    $terms = $this->termStorage->loadTree($this->vocabulary);
    $fp = fopen('php://memory', 'rw');
    $standardTaxonomyFields = [
      'tid',
      'uuid',
      'langcode',
      'vid',
      'name',
      'status',
      'revision_id',
      'description__value',
      'description__format',
      'weight',
      'parent_name',
      'parent',
      'changed',
      'default_langcode',
      'path',
    ];
    $to_export = [];

    if ($include_headers) {
      $to_export = [
        'name',
        'status',
        'description__value',
        'description__format',
        'weight',
        'parent_name',
      ];
      if ($include_ids) {
        $to_export = array_merge(['tid', 'uuid'], $to_export);
        $to_export[] = 'parent_tid';
        array_splice($to_export, 4, 0, 'revision_id');
      }
      if ($include_fields) {
        $to_export[] = 'fields';
      }
    }
    fputcsv($fp, $to_export);
    foreach ($terms as $term) {
      $parents = $this->termStorage->loadParents($term->tid);
      $parent_names = '';
      $parent_ids = '';
      $to_export = [];
      if (!empty($parents)) {
        foreach ($parents as $parent) {
          $parent_names .= $parent->getName() . ';';
          $parent_ids .= $parent->id() . ';';
        }
      }
      $to_export = [
        $term->name,
        $term->status,
        $term->description__value,
        $term->description__format,
        $term->weight,
        $parent_names,
      ];
      if ($include_ids) {
        $to_export = array_merge([$term->tid, $this->termStorage->load($term->tid)->uuid()], $to_export);
        $to_export[] = $parent_ids;
        array_splice($to_export, 4, 0, $this->termStorage->load($term->tid)->getRevisionId());
      }
      if ($include_fields) {
        $field_export = [];
        foreach ($this->termStorage->load($term->tid)->getFields() as $field) {
          if (!in_array($field->getName(), $standardTaxonomyFields)) {
            foreach ($field->getValue() as $values) {
              foreach ($values as $value) {
                // Skip type ($key) here. More complicated, seems unnecessary.
                $field_export[$field->getName()][] = $value;
              }
            }
          }
        }
        $fields = http_build_query($field_export);
        $to_export[] = $fields;
      }
      fputcsv($fp, $to_export);
    }
    rewind($fp);
    while (!feof($fp)) {
      $this->export .= fread($fp, 8192);
    }
    fclose($fp);
    return $this->export;
  }

}
