<?php

namespace Drupal\media_extra\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\media\MediaInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\media\Plugin\media\Source\OEmbedInterface;
use Drupal\media\Plugin\media\Source\File as FileSource;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'generic_media_link' formatter.
 *
 * @FieldFormatter(
 *   id = "generic_media_link",
 *   label = @Translation("Generic Media Link"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class GenericMediaLinkFormatter extends FileFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

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
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->renderer = $renderer;
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
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'link_text' => '',
      'use_url_as_link_text' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $build_info = $form_state->getBuildInfo();
    if ($build_info['form_id'] == 'entity_embed_dialog') {
      $parent_path = 'attributes[data-entity-embed-display-settings]';
    }
    else if ($build_info['form_id'] == 'entity_view_display_edit_form') {
      // TODO: Support entity view display edit form.
      $parent_path = '';
    }

    // $media = $form_state->get('entity');

    $element['use_url_as_link_text'] = [
      '#title' => t('Use URL itself as link text'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('use_url_as_link_text'),
    ];

    $element['link_text'] = [
      '#title' => t('Link text'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('link_text'),
      '#description' => t('Enter custom link to use. If empty media name will be used as text.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $parent_path . '[use_url_as_link_text]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $custom_link_text = $this->getSetting('link_text');

    if (empty($custom_link_text)) {
      $summary[] = $this->t('Linked with media name');
    }
    else {
      $summary[] = $this->t('Linked with custom text "@custom"', ['@custom' => $custom_link_text]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    $custom_link_text = $this->getSetting('link_text');
    $use_url_as_link_text = $this->getSetting('use_url_as_link_text');

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      $media_type  = $media->getEntityType();
      $source = $media->getSource();
      if ($source instanceof OEmbedInterface) {
        $link_url = $source->getMetadata($media, 'url');
        if (empty($link_url)) {
          $link_url = $source->getSourceFieldValue($media);
        }

        if ($use_url_as_link_text) {
          $link_text = $link_url;
        }
        else {
          if (!empty($custom_link_text)) {
            $link_text = $custom_link_text;
          }
          else {
            $link_text = $source->getMetadata($media, 'default_name');
          }
        }

        $elements[$delta] = [
          '#type' => 'link',
          '#url' => Url::fromUri($link_url),
          '#title' => $link_text,
          '#options' => [
            'attributes' => [
              'class' => [$source->getMetadata($media, 'provider_name')]
            ]
          ]
        ];
      }
      else if ($source instanceof FileSource) {
        $source_field = $media->getSource()->getConfiguration()['source_field'];
        $file = $media->get($source_field)->entity;

        if ($use_url_as_link_text) {
          $link_text = $file->createFileUrl(FALSE);
        }
        else {
          $link_text = !empty($custom_link_text) ? $custom_link_text : $media->getName();
        }
        if ($file) {
          $elements[$delta] = [
            '#theme' => 'file_link',
            '#file' => $file,
            '#description' => $link_text,
            '#cache' => [
              'tags' => $file->getCacheTags(),
            ],
          ];
        }
      }

      // Add cacheability of each item in the field.
      $this->renderer->addCacheableDependency($elements[$delta], $media);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only available for entity types that reference
    // media items.
    return ($field_definition->getFieldStorageDefinition()->getSetting('target_type') == 'media');
  }

}
