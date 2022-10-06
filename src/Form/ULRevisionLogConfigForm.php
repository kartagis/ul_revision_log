<?php

namespace Drupal\ul_revision_log\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * ULRevisionLogConfigForm class for the setting of content types.
 */
class ULRevisionLogConfigForm extends ConfigFormBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs an AutoParagraphForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ul_revision_log_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Default settings.
    $config = $this->config('ul_revision_log.settings');

    // Setting of content types.
    $form['content_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Set Content Types for Revision Log Required'),
      '#default_value' => $config->get('ul_revision_log.values'),
      '#description' => $this->t('Enter the Content Type IDs: <br>* - One word or words connected by "_" (underscore) <br>* - All lowercase letters<br>* - Separate Type IDs with a whitespace'),
    ];

    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $fields = $form_state->getValue('fields');
    if (empty($fields)) {
      return TRUE;
    }
    $fields_arr = explode(" ", $fields);
    $error = [];

    $contentTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    foreach ($fields_arr as $field_name) {
      if (!$this->isValideField(trim($field_name), $contentTypes)) {
        $error[] = $field_name;
      }
    }

    if (!empty($error)) {
      $str = "Not valid field(s): " . implode(",", $error) . ".";
      $form_state->setErrorByName('fields', $str);
    }
    else {
      return TRUE;
    }

  }


  /**
   * Check if a field is valid.
   *
   * @param string $field_item
   *   The field name.
   * @param array $contentTypes
   *   The field name.
   *
   * @return bool
   *   TRUE of FALSE.
   */
  protected function isValideField($field_item, array &$contentTypes) {
    foreach ($contentTypes as $contentType) {
      if ($contentType->id() == $field_item) {
        $return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('ul_revision_log.settings');
    $config->set('ul_revision_log.values', $form_state->getValue('content_types'));
    $config->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ul_revision_log.settings',
    ];
  }

}
