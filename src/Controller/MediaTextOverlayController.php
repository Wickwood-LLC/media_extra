<?php

namespace Drupal\media_extra\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaTextOverlayController extends ControllerBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new MediaTextOverlayController object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *  The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  public function edit(MediaInterface $media) {
    $form = \Drupal::service('entity.form_builder')
      ->getForm($media, 'overlay_text_edit');
    return $form;
  }

  public function hasTextOverlayField(MediaInterface $media) {
    if ($this->moduleHandler->moduleExists('media_text_overlay')) {
      $field_definitions = $media->getFieldDefinitions();
      foreach ($field_definitions as $field_name => $field_definition) {
        if ($field_definition->getType() === 'media_text_overlay') {
          return AccessResult::allowed();
          break;
        }
      }
    }
    return AccessResult::forbidden();
  }
}
