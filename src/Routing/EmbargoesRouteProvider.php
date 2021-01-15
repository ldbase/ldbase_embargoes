<?php

namespace Drupal\ldbase_embargoes\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes to delete LDbase content
 */
class EmbargoesRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $route_collection = new RouteCollection();
    // create routes for these content types
    $ldbase_bundles = array('dataset', 'code', 'document');
    foreach ($ldbase_bundles as $ldbase_bundle) {
      $pluralizer = ($ldbase_bundle == 'code') ? '' : 's';
      // create routes for each content type, going to same delete form
      $route = new Route(
        // path:
        '/' . $ldbase_bundle . $pluralizer. '/{node}/request-access',
        // Route defaults:
        [
          '_controller' => '\Drupal\ldbase_embargoes\Controller\RequestAccessController::requestAccess',
          '_title_callback' => '\Drupal\ldbase_embargoes\Controller\RequestAccessController::getRequestFormTitle',
        ],
        // Requirements:
        [
          '_permission' => 'access content',
        ],
        // Options:
        [
          'parameters' => [
            'node' => [
              'type' => 'ldbase_uuid'
            ]
          ]
        ]
      );

      // Add the route to the collection
      $route_collection->add("ldbase_embargoes.request_{$ldbase_bundle}_embargo_access", $route);
    }

    return $route_collection;
  }

}
