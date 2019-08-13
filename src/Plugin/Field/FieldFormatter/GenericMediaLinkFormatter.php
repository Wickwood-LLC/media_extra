<?php

namespace Drupal\media_extra\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\media\MediaInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;

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
class GenericMediaLinkFormatter extends FileFormatterBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

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
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['link_text'] = [
      '#title' => t('Link text'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('link_text'),
      '#description' => t('Enter custom link to use. If empty media name will be used as text.'),
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

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      $media_type  = $media->getEntityType();
      //$source_field = $media_type->getSource()->getConfiguration()['source_field'];
      $source_field = $media->getSource()->getConfiguration()['source_field'];
      $file = $media->get($source_field)->entity;
      $item = $file->_referringItem;
      $elements[$delta] = [
        '#theme' => 'file_link',
        '#file' => $file,
        '#description' => !empty($custom_link_text) ? $custom_link_text : $media->getName(),
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];
      // Pass field item attributes to the theme function.
      if (isset($item->_attributes)) {
        $elements[$delta] += ['#attributes' => []];
        $elements[$delta]['#attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }

      // Add cacheability of each item in the field.
      //$this->renderer->addCacheableDependency($elements[$delta], $media);
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
