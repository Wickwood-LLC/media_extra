<?php

namespace Drupal\media_extra\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Media Type settings entity.
 *
 * @ConfigEntityType(
 *   id = "media_type_settings",
 *   label = @Translation("Media Type Settings"),
 *   handlers = {},
 *   config_prefix = "media_type_settings",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   config_export = {
 *     "id",
 *     "name_optional",
 *     "name_description"
 *   },
 *   links = {}
 * )
 */
class MediaTypeSettings extends ConfigEntityBase {

  /**
   * Media Type settings id. It will be media type bundle name.
   *
   * @var string
   */
  public $id;

  /**
   * Whether the name field is optional or not.
   */
  public $name_optional;

  /**
   * Custom description to be used for Name field.
   */
  public $name_description;

  public static function load($id) {
    $entity =  \Drupal::entityTypeManager()
      ->getStorage('media_type_settings')
      ->load($id);
    if (!$entity) {
      $entity = \Drupal::entityTypeManager()->getStorage('media_type_settings')
       ->create(['id' => $load, 'name_optional' => FALSE, 'name_description' => '']);
    }
    return $entity;
  }
  
}
