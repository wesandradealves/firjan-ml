<?php

namespace Drupal\single_content_sync\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\single_content_sync\ContentFileGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to export a content.
 *
 * @package Drupal\single_content_sync\Form
 */
class ContentBulkExportForm extends ConfirmFormBase {

  /**
   * The private temp store of the module.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $privateTempStore;

  /**
   * The custom file generator to export content.
   *
   * @var \Drupal\single_content_sync\ContentFileGeneratorInterface
   */
  protected ContentFileGeneratorInterface $fileGenerator;

  /**
   * Construct of ContentBulkExportForm.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private temp store of the module.
   * @param \Drupal\single_content_sync\ContentFileGeneratorInterface $file_generator
   *   The custom file generator to export content.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, ContentFileGeneratorInterface $file_generator) {
    $this->privateTempStore = $temp_store_factory->get('single_content_sync');
    $this->fileGenerator = $file_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('single_content_sync.file_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'single_content_sync_bulk_export_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to export these content?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('system.admin_content');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Export');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entities = $this->privateTempStore->get($this->currentUser()->id());

    if (!$entities) {
      $this->messenger()->addError($this->t('The content from the action "Export content" was not found.'));
      return $this->redirect('system.admin_content');
    }

    $form['content'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Content to export'),
      '#items' => array_map(function (EntityInterface $entity) {
        return $this->t('@label in %translation', [
          '@label' => $entity->label(),
          '%translation' => $entity->language()->getName(),
        ]);
      }, $entities),
    ];

    $form['assets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include all assets'),
      '#description' => $this->t('Whether to export all file assets such as images, documents, videos and etc.'),
    ];

    $form['translation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include all translations'),
      '#description' => $this->t('Whether to export available translations of the content.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entities = $this->privateTempStore->get($this->currentUser()->id());

    // Clean up the storage after successful operation.
    $this->privateTempStore->delete($this->currentUser()->id());

    if ($form_state->getValue('confirm')) {
      $extract_translations = $form_state->getValue('translation', FALSE);
      $extract_assets = $form_state->getValue('assets', FALSE);
      $file = $this->fileGenerator->generateBulkZipFile($entities, $extract_translations, $extract_assets);

      [$file_scheme, $file_target] = explode('://', $file->getFileUri(), 2);
      $this->messenger()->addStatus($this->t('We have successfully exported the chosen content. Follow the @link to download the generate zip file with the content', [
        '@link' => Link::createFromRoute($this->t('link'), 'single_content_sync.file_download', ['scheme' => $file_scheme], [
          'query' => [
            'file' => $file_target,
          ],
        ])->toString(),
      ]));
    }

    $form_state->setRedirect('system.admin_content');
  }

}
