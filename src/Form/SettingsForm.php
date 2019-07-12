<?php

namespace Drupal\media_extra\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Media Extra settings.
 */
class SettingsForm extends ConfigFormBase {
  /** @var string Config settings */
  const SETTINGS = 'media_extra.settings';

  public function getFormId() {
    return 'media_extra_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['allowed_image_styles_for_static_image'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed image styles for static image'),
      '#default_value' => $config->get('allowed_image_styles_for_static_image'),
      '#options' => image_style_options(FALSE),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
     $this->configFactory->getEditable(static::SETTINGS)
    // Set the submitted editor CSS setting
    ->set('allowed_image_styles_for_static_image', $form_state->getValue('allowed_image_styles_for_static_image'))
    ->save();

    parent::submitForm($form, $form_state);
  }
}