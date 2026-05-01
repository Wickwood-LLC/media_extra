<?php

namespace Drupal\media_extra\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class Routes {

  /**
   * Provides dynamic routes.
   */
  public function routes() {
    $routes = [];
    // Declares a single route under the name 'example.content'.
    // Returns an array of Route objects. 
    $routes['media.edit.overlay_text'] = new Route(
      // Path to attach this route to:
      '/media/{media}/edit/overlay-text',
      // Route defaults:
      [
        '_controller' => '\Drupal\media_extra\Controller\MediaTextOverlayController::edit',
      ],
      // Route requirements:
      [
        '_entity_access' => 'media.update',
        '_custom_access' => '\Drupal\media_extra\Controller\MediaTextOverlayController::hasTextOverlayField',
      ],
      [
        // '_admin_route' => TRUE,
      ]
    );

    $routes['media.edit.image_tester'] = new Route(
      // Path to attach this route to:
      '/media/{media}/edit/og-image-tester',
      // Route defaults:
      [
        '_controller' => '\Drupal\media_extra\Controller\MediaTextOverlayController::imageTester',
      ],
      // Route requirements:
      [
        '_entity_access' => 'media.update',
        '_custom_access' => '\Drupal\media_extra\Controller\MediaTextOverlayController::hasTextOverlayField',
      ],
      [
        '_admin_route' => TRUE,
      ]
    );

    return $routes;
  }

}
