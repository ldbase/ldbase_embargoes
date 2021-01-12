<?php

namespace Drupal\ldbase_embargoes\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for embargoed nodes.
 */
class EmbargoedNodeAccess extends EmbargoedAccessResult {

  /**
   * {@inheritdoc}
   */
  public static function entityType() {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function isActivelyEmbargoed(EntityInterface $node, AccountInterface $user) {
    $state = parent::isActivelyEmbargoed($node, $user);
    $embargoes = $this->embargoes->getActiveNodeEmbargoesByNids([$node->id()], $user);
    if (!empty($embargoes)) {
      $state = AccessResult::forbidden();
      $state->addCacheableDependency($node);
      $state->addCacheableDependency($user);
    }
    return $state;
  }

}
