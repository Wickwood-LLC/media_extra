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

  const LINK_TEXT_TYPE_MEDIA_LABEL = 'media_label';
  const LINK_TEXT_TYPE_FILENAME = 'filename';
  const LINK_TEXT_TYPE_URL = 'url';
  const LINK_TEXT_TYPE_CUSTOM = 'custom';

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
      'link_text_type' => self::LINK_TEXT_TYPE_FILENAME,
      'display_file_type' => FALSE,
      'display_icon' => FALSE,
      'display_file_size' => FALSE,
      'icon_position' => 'before',
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
    else if ($build_info['form_id'] == 'layout_builder_update_block') {
      $parent_path = 'settings[formatter][settings]';
    }

    // $media = $form_state->get('entity');

    $element['link_text_type'] = [
      '#title' => t('Link Text Type'),
      '#type' => 'radios',
      '#options' => [
        self::LINK_TEXT_TYPE_MEDIA_LABEL => $this->t('Media Label'),
        self::LINK_TEXT_TYPE_FILENAME => $this->t('File Name'),
        self::LINK_TEXT_TYPE_URL => $this->t('URL'),
        self::LINK_TEXT_TYPE_CUSTOM => $this->t('Custom Text'),
      ],
      '#default_value' => $this->getSetting('link_text_type'),
    ];

    $element['link_text'] = [
      '#title' => t('Link text'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('link_text'),
      '#description' => t('Enter custom link to use. If empty media name will be used as text.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $parent_path . '[link_text_type]"]' => ['value' => self::LINK_TEXT_TYPE_CUSTOM],
        ],
      ],
    ];

    $element['display_file_type'] = [
      '#title' => t('Display File Type'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('display_file_type'),
      '#description' => t('Select to display file type along with the link.'),
    ];

    $element['display_icon'] = [
      '#title' => t('Display Icon'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('display_icon'),
      '#description' => t('Select to display icon along with the link.'),
    ];

    $element['display_file_size'] = [
      '#title' => t('Display File Size'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('display_file_size'),
      '#description' => t('Select to display file size along with the link.'),
    ];

    $element['icon_position'] = [
      '#title' => t('Icon Position'),
      '#type' => 'radios',
      '#default_value' =>  $this->getSetting('icon_position'),
      '#options' => [
        'before' => $this->t('Before'),
        'after' => $this->t('After'),
      ],
      '#description' => t('Select the position where icon to be displayed along with the link.'),
      '#states' => [
        'visible' => [
          ':input[name="' . $parent_path . '[display_icon]"]' => ['checked' => TRUE],
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
    $link_text_type = $this->getSetting('link_text_type');

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      $media_type  = $media->getEntityType();
      $source = $media->getSource();
      if ($source instanceof OEmbedInterface) {
        $link_url = $source->getMetadata($media, 'url');
        if (empty($link_url)) {
          $link_url = $source->getSourceFieldValue($media);
        }

        if ($link_text_type == self::LINK_TEXT_TYPE_URL) {
          $link_text = $link_url;
        }
        elseif ($link_text_type == self::LINK_TEXT_TYPE_CUSTOM && !empty($custom_link_text)) {
          $link_text = $custom_link_text;
        }
        else {
          $link_text = $source->getMetadata($media, 'default_name');
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
        $options = [
          'file_type' => $this->getSetting('display_file_type'),
          'icon' => $this->getSetting('display_icon'),
          'file_size' => $this->getSetting('display_file_size'),
        ];

        if ($link_text_type == self::LINK_TEXT_TYPE_MEDIA_LABEL) {
          $link_text = $media->label();
        }
        elseif ($link_text_type == self::LINK_TEXT_TYPE_URL) {
          $link_text = $file->createFileUrl(FALSE);
        }
        elseif ($link_text_type == self::LINK_TEXT_TYPE_CUSTOM && !empty($custom_link_text)) {
          $link_text = $custom_link_text;
        }
        else {
          // Use self::LINK_TEXT_TYPE_FILENAME as fallback.
          $link_text = $file->getFilename();
        }
        if ($file) {
          $elements[$delta] = [
            '#theme' => 'media_extra_file_link',
            '#file' => $file,
            '#description' => $link_text,
            '#options' => $options,
            '#icon_position' => $this->getSetting('icon_position'),
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
