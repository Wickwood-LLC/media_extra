<?php

namespace Drupal\media_extra\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\embed\DomHelperTrait;

/**
 * Provides a filter to align elements.
 *
 * @Filter(
 *   id = "me_filter_rotate",
 *   title = @Translation("Rotate images"),
 *   description = @Translation("Uses a <code>data-rotate</code> attribute on <code>&lt;img&gt;</code> tags to rotate images. When used with caption filter this should come before the caption filter."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE
 * )
 */
class FilterRotate extends FilterBase {
  use DomHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, 'data-rotate') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//*[@data-rotate]') as $node) {
        // Read the data-align attribute's value, then delete it.
        $rotate = $node->getAttribute('data-rotate');
        $node->removeAttribute('data-rotate');

        $rotate_caption = $node->getAttribute('data-rotate-caption');
        $node->removeAttribute('data-rotate-caption');

        $classes = $node->getAttribute('class');
        $classes = (strlen($classes) > 0) ? explode(' ', $classes) : [];
        // If one of the allowed alignments, add the corresponding class.
        if (in_array($rotate, ['left', 'right'])) {
          $classes[] = 'rotate-' . $rotate;

          if (!empty($rotate_caption)) {
            $classes[] = 'rotate-caption';
          }
          else {
            $classes[] = 'caption-not-rotated';
          }

          $context = $this->getNodeAttributesAsArray($node);
          if (!empty($context['data-entity-embed-display-settings']['image_style'])) {
            $classes[] = 'image-style--' . str_replace('_', '-', $context['data-entity-embed-display-settings']['image_style']);
          }
        }
        else {
          $classes[] = 'not-rotated';
        }
        $node->setAttribute('class', implode(' ', $classes));

      }
      $result->setProcessedText(Html::serialize($dom));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
        <p>You can rotate images, videos, blockquotes and so on to the left, right or center. Examples:</p>
        <ul>
          <li>Rotate an image to the left: <code>&lt;img src="" data-rotate="left" /&gt;</code></li>
          <li>Rotate an image to the right: <code>&lt;img src="" data-rotate="right" /&gt;</code></li>
          <li>… and you can apply this to other elements as well: <code>&lt;video src="" data-rotate="left" /&gt;</code></li>
        </ul>');
    }
    else {
      return $this->t('You can rotate images (<code>data-rotate="left"</code>), but also videos, blockquotes, and so on.');
    }
  }

}
