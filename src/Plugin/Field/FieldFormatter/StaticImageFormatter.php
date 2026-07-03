<?php

namespace Drupal\media_extra\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Render\RendererInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\image\ImageDerivativeUtilities;

/**
 * Plugin implementation of the 'media_thumbnail' formatter.
 *
 * @FieldFormatter(
 *   id = "static_image",
 *   label = @Translation("Media Image"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class StaticImageFormatter extends MediaThumbnailFormatter {

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
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $link_generator
   *   The link generator service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, ImageStyleStorageInterface $image_style_storage, FileUrlGeneratorInterface $file_url_generator, RendererInterface $renderer, protected ImageDerivativeUtilities $imageDerivativeUtilities, LinkGeneratorInterface $link_generator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $current_user, $image_style_storage, $file_url_generator, $renderer, $imageDerivativeUtilities);
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
      $container->get('file_url_generator'),
      $container->get('renderer'),
      $container->get(ImageDerivativeUtilities::class),
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
    $allowed_image_styles = $config->get('allowed_image_styles_for_static_image');
    $element['image_style']['#options'] = array_intersect_key($element['image_style']['#options'], array_filter($allowed_image_styles));
    $element['image_style']['#description'] = [
      '#markup' => $this->linkGenerator->generate($this->t('Configure allowed Image Styles'), new Url('entity.media_extra.settings')),
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
        '#element_validate' => [[get_class($this), 'validateLink']],
      ];
    }

    return $element;
  }

  /**
   * Validate the entered link.
   */
  public static function validateLink($element,  FormStateInterface $form_state) {
    $url = NULL;
    $href = $form_state->getValue($element['#parents']);

    if (empty($href)) {
      return;
    }

    try {
      $url = Url::fromUserInput($href);
    }
    catch (\InvalidArgumentException $e) {
      try {
        $url = Url::fromUri($href);
      }
      catch (\InvalidArgumentException $e) {
      }
    }
    if ($url) {
      try {
        $url->toString();
      }
      catch (\InvalidArgumentException $e) {
        $url = NULL;
      }
    }
    if (!$url) {
      $form_state->setError($element, t('Please enter a valid path or external URL.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getMediaThumbnailUrl(MediaInterface $media, EntityInterface $entity) {
    $url = NULL;
    if (\Drupal::service('module_handler')->moduleExists('linkit')) {
      $href = $this->getSetting('linkit');
      if (!empty($href)) {
        try {
          $url = Url::fromUserInput($href);
        }
        catch (\InvalidArgumentException $e) {
          $url = Url::fromUri($href);
        }
      }
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    $media_items = $this->getEntitiesToView($items, $langcode);

    foreach (Element::children($elements) as $delta) {
      /** @var MediaInterface $media */
      $media = $media_items[$delta];
      $source_field_name = static::getMediaImageSourceField($media);
      if ($source_field_name) {
        $elements[$delta]['#item'] = $media->get($source_field_name)->first();
      }
    }

    return $elements;
  }

  /**
   * Get image field from source config.
   * copy of MediaEmbed::getMediaImageSourceField()
   *
   * @param \Drupal\media\MediaInterface $media
   *   A media entity.
   *
   * @return string|null
   *   String of image field name.
   */
  public static function getMediaImageSourceField(MediaInterface $media) {
    $field_definition = $media->getSource()
      ->getSourceFieldDefinition($media->bundle->entity);
    $item_class = $field_definition->getItemDefinition()->getClass();
    if ($item_class == ImageItem::class || is_subclass_of($item_class, ImageItem::class)) {
      return $field_definition->getName();
    }
    return NULL;
  }

}
