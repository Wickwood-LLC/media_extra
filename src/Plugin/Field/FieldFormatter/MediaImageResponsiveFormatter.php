<?php

namespace Drupal\media_extra\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Plugin implementation of the 'media_image_responsive' formatter.
 *
 * @FieldFormatter(
 *   id = "media_image_responsive",
 *   label = @Translation("Responsive Media Image"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MediaImageResponsiveFormatter extends MediaThumbnailFormatter {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $responsiveImageStyleStorage;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * Constructs an MediaThumbnailFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\image\ImageStyleStorageInterface $image_style_storage
   *   The image style entity storage handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $responsive_image_style_storage
   *   The responsive image style storage.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, ImageStyleStorageInterface $image_style_storage, RendererInterface $renderer, EntityStorageInterface $responsive_image_style_storage, LinkGeneratorInterface $link_generator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $renderer);
    $this->responsiveImageStyleStorage = $responsive_image_style_storage;
    $this->linkGenerator = $link_generator;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('renderer'),
      $container->get('entity_type.manager')->getStorage('responsive_image_style'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'linkit' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $config = \Drupal::config('media_extra.settings');

    $responsive_image_options = [];
    $responsive_image_styles = $this->responsiveImageStyleStorage->loadMultiple();
    if ($responsive_image_styles && !empty($responsive_image_styles)) {
      foreach ($responsive_image_styles as $machine_name => $responsive_image_style) {
        if ($responsive_image_style->hasImageStyleMappings()) {
          $responsive_image_options[$machine_name] = $responsive_image_style->label();
        }
      }
    }

    $allowed_responsive_image_styles = $config->get('allowed_image_styles_for_responsive_image');

    // Replace regular image styles list with responsive image styles.
    $element['image_style']['#options'] = array_intersect_key($responsive_image_options, array_filter($allowed_responsive_image_styles));;
    // Change title accordingly.
    $element['image_style']['#title'] = $this->t('Responsive image style');
    // description as well.
    $element['image_style']['#description'] = [
      '#markup' => $this->linkGenerator->generate($this->t('Configure allowed Responsive Image Styles'), new Url('entity.media_extra.settings')),
      '#access' => $this->currentUser->hasPermission('administer media'),
    ];

    unset($element['image_link']);

    if (\Drupal::service('module_handler')->moduleExists('linkit')) {
      $element['linkit'] = [
        '#title' => $this->t('Link'),
        '#type' => 'linkit',
        '#default_value' => $this->getSetting('linkit'),
        '#description' => $this->t('Start typing to find content or paste a URL.'),
        '#autocomplete_route_name' => 'linkit.autocomplete',
        '#autocomplete_route_parameters' => [
          'linkit_profile_id' => $config->get('linkit_profile'),
        ],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    foreach ($elements as $delta => &$element) {
      $element['#theme'] = 'responsive_image_formatter';
      $element['#responsive_image_style_id'] = $element['#image_style'];
      unset($element['#image_style']);
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $style_id = $this->getSetting('image_style');
    /** @var \Drupal\responsive_image\ResponsiveImageStyleInterface $style */
    if ($style_id && $style = ResponsiveImageStyle::load($style_id)) {
      // Add the responsive image style as dependency.
      $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $changed = parent::onDependencyRemoval($dependencies);
    $style_id = $this->getSetting('image_style');
    /** @var\Drupal\responsive_image\ResponsiveImageStyleInterface $style */
    if ($style_id && $style = ResponsiveImageStyle::load($style_id)) {
      if (!empty($dependencies[$style->getConfigDependencyKey()][$style->getConfigDependencyName()])) {
        $replacement_id = $this->imageStyleStorage->getReplacementId($style_id);
        // If a valid replacement has been provided in the storage, replace the
        // image style with the replacement and signal that the formatter plugin
        // settings were updated.
        if ($replacement_id && ResponsiveImageStyle::load($replacement_id)) {
          $this->setSetting('image_style', $replacement_id);
          $changed = TRUE;
        }
      }
    }
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMediaThumbnailUrl(MediaInterface $media, EntityInterface $entity) {
    $url = NULL;
    if (\Drupal::service('module_handler')->moduleExists('linkit')) {
      $href = $this->getSetting('linkit');
      if (!empty($href)) {
        $url = Url::fromUserInput($href);
      }
    }
    return $url;
  }

}
