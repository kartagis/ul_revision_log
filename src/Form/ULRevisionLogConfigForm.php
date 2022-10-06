<?php

namespace Drupal\ul_revision_log\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * ULRevisionLogConfigForm class for the setting of content types.
 */
class ULRevisionLogConfigForm extends ConfigFormBase {

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
      '#title' => $this->t('Set Content Types for Ul Revision Log'),
      '#default_value' => $config->get('ul_revision_log.values'),
      '#description' => $this->t('Enter the Content Type IDs: <br>* - One word or words connected by "_" (underscore) <br>* - All lowercase letters<br>* - Separate Type IDs with a whitespace'),
    ];

    return parent::buildForm($form, $form_state);
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
