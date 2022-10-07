<?php

namespace Drupal\ul_revision_log\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\content_moderation\ModerationInformation;
use Drupal\content_moderation\StateTransitionValidation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'moderation_state_default' widget.
 *
 * @FieldWidget(
 *   id = "cm_submit_buttons",
 *   label = @Translation("Content moderation submit buttons"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class CMSubmitButtonsWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Moderation state transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidation
   */
  protected $validator;

  /**
   * Constructs a new ModerationStateWidget object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\content_moderation\ModerationInformation $moderation_information
   *   Moderation information service.
   * @param \Drupal\content_moderation\StateTransitionValidation $validator
   *   Moderation state transition validation service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, ModerationInformation $moderation_information, StateTransitionValidation $validator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->moderationInformation = $moderation_information;
    $this->validator = $validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $entity = $items->getEntity();
    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return [];
    }
    return parent::form($items, $form, $form_state, $get_delta);
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $items->getEntity();

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    $default = $items->get($delta)->value ? $workflow->getTypePlugin()->getState($items->get($delta)->value) : $workflow->getTypePlugin()->getInitialState($entity);

    /** @var \Drupal\workflows\Transition[] $transitions */
    $transitions = $this->validator->getValidTransitions($entity, $this->currentUser);
    if (!$transitions) {
      return $element;
    }

    $target_states = [];
    $transition_data = [];
    foreach ($transitions as $transition) {
      $target_states[$transition->to()->id()] = $transition->label();
      $transition_data[$transition->to()->id()] = [
        'transition_machine_name' => $transition->id(),
      ];
    }
    $tempstore = \Drupal::service('tempstore.private')->get('cm_submit');
    $form_id = $form_state->getBuildInfo()['form_id'];
    $tempstore->set($form_id . '_transition_data', $transition_data);

    $element += [
      '#access' => FALSE,
      '#type' => 'select',
      '#options' => $target_states,
      '#default_value' => $default->id(),
      '#published' => $default->isPublishedState(),
      '#key_column' => $this->column,
    ];
    $element['#element_validate'][] = [get_class($this), 'validateElement'];

    // Following dropbutton's approach, we'll break out our element into buttons
    // in a separate process, which should be called more alter than process?
    $element['#process'][] = [get_called_class(), 'processActions'];

    $element['#show_current_state'] = $default->label();

    return $element;
  }

  /**
   * Entity builder updating the node moderation state with the submitted value.
   *
   * @param string $entity_type_id
   *   The entity type identifier.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity updated with the submitted values.
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function updateStatus($entity_type_id, ContentEntityInterface $entity, array $form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    if (isset($element['#moderation_state'])) {
      $entity->moderation_state->value = $element['#moderation_state'];
    }
  }

  /**
   * Process callback to alter submit action buttons.
   */
  public static function processActions($element, FormStateInterface $form_state, array &$form) {
    $default_button = $form['actions']['submit'];
    $default_button['#access'] = TRUE;

    $options = $element['#options'];

    $tempstore = \Drupal::service('tempstore.private')->get('cm_submit');
    $form_id = $form_state->getBuildInfo()['form_id'];
    $transition_data = $tempstore->get($form_id . '_transition_data');
    if (!$transition_data) {
      $form_id = $form_state->getBuildInfo()['form_id'];
      \Drupal::logger('cm_submit')->alert('Something weird is happening, there is no transition data for form @id', ['@id' => $form_id]);
      return;
    }

    $weight = -100;
    foreach ($options as $id => $label) {
      $button = [
        // Comment out the line below if you want to show the buttons separately
        // instead of dropdown.
        '#dropbutton' => 'save',
        '#moderation_state' => $id,
        '#weight' => $weight + 10,
      ];

      if (isset($transition_data[$id])) {
        $transition_machine_name = $transition_data[$id]['transition_machine_name'];
      }
      else {
        \Drupal::logger('cm_submit')->alert('There is no transition data for @id', ['@id' => $id]);
      }

      $button['#value'] = t('Save and @transition (this translation)', ['@transition' => $label]);

      if (strpos($form['#form_id'], "edit") > 1 || $button['#moderation_state'] != "archived") {
        $form['actions']['moderation_state_' . $id] = $button + $default_button;
      }

    }

    // Hide the Published checkbox.
    unset($form['status']);

    // Hide the default buttons, including the specialty ones added by
    // NodeForm.
    foreach (['publish', 'unpublish', 'submit'] as $key) {
      $form['actions'][$key]['#access'] = FALSE;
    }

    // Set a callback to transform the button selection back into a field
    // widget, so that it will get saved properly.
    // $form['#entity_builders']['update_moderation_state'] = [get_called_class(), 'updateStatus'];.
    $form['meta']['current_moderation_state'] = [
      '#type' => 'item',
      '#title' => t('Moderation State'),
      '#markup' => t('@current_moderation_state', ['@current_moderation_state' => $element['#show_current_state']]),
      '#wrapper_attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'moderation_state';
  }

}
