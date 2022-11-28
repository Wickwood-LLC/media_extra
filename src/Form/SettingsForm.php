<?php

namespace Drupal\media_extra\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media Extra settings.
 */
class SettingsForm extends ConfigFormBase {
  /** @var string Config settings */
  const SETTINGS = 'media_extra.settings';

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $responsiveImageStyleStorage;

  /**
   * Constructs a new SiteConfigureForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityStorageInterface $responsive_image_style_storage
   *   The responsive image style storage.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityStorageInterface $responsive_image_style_storage) {
    parent::__construct($config_factory);
    $this->responsiveImageStyleStorage = $responsive_image_style_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')->getStorage('responsive_image_style')
    );
  }

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
      '#title' => $this->t('Allowed image styles for Media Image formatter'),
      '#default_value' => $config->get('allowed_image_styles_for_static_image'),
      '#options' => image_style_options(FALSE),
    ];

    $responsive_image_options = [];
    $responsive_image_styles = $this->responsiveImageStyleStorage->loadMultiple();
    if ($responsive_image_styles && !empty($responsive_image_styles)) {
      foreach ($responsive_image_styles as $machine_name => $responsive_image_style) {
        if ($responsive_image_style->hasImageStyleMappings()) {
          $responsive_image_options[$machine_name] = $responsive_image_style->label();
        }
      }
    }

    $form['allowed_image_styles_for_responsive_image'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed responsive image styles for Responsive Media Image formatter'),
      '#default_value' => $config->get('allowed_image_styles_for_responsive_image') ?: [],
      '#options' => $responsive_image_options,
    ];


    if (\Drupal::service('module_handler')->moduleExists('linkit')) {
      $linkitProfileStorage = \Drupal::entityTypeManager()->getStorage('linkit_profile');

      $all_profiles = $linkitProfileStorage->loadMultiple();

      $options = array();
      foreach ($all_profiles as $profile) {
        $options[$profile->id()] = $profile->label();
      }

      $form['linkit_profile'] = array(
        '#type' => 'select',
        '#title' => t('Linkit profile'),
        '#options' => $options,
        '#default_value' => $config->get('linkit_profile') ?: '',
        '#empty_option' => $this->t('- Select profile -'),
        '#description' => $this->t('Select the linkit profile you wish to use with the Media Image and Responsive Media Image formatters.'),
        // '#element_validate' => array(
        //   array($this, 'validateLinkitProfileSelection'),
        // ),
      );
    }

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
    ->set('allowed_image_styles_for_responsive_image', $form_state->getValue('allowed_image_styles_for_responsive_image'))
    ->set('linkit_profile', $form_state->getValue('linkit_profile'))
    ->save();

    parent::submitForm($form, $form_state);
  }
}