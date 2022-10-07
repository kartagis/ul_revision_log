<?php

namespace Drupal\ul_revision_log\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ULRevisionLogConfigForm class for the setting of content types.
 */
class ULRevisionLogConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager =  $container->get('entity_type.manager');
    return $instance;
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
    $fields = $form_state->getValue('content_types');
    if (empty($fields)) {
      return TRUE;
    }
    $fields_arr = explode(" ", $fields);
    $error = [];

    $contentTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();

    foreach ($fields_arr as $name) {
      if (!$this->isValideField(trim($name), $contentTypes)) {
        $error[] = $name;
      }
    }

    if (!empty($error)) {
      $str = "Not valid field(s): " . implode(",", $error) . ".";
      $form_state->setErrorByName('content_types', $str);
    }
    else {
      return TRUE;
    }

  }

  /**
   * Check if a field is valid.
   *
   * @param string $name
   *   The field name.
   * @param array $contentTypes
   *   The field name.
   *
   * @return bool
   *   TRUE of FALSE.
   */
  protected function isValideField($name, array &$contentTypes) {
    foreach ($contentTypes as $contentType) {
      if ($contentType->id() == $name) {
        return TRUE;
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
