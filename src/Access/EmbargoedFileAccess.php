<?php

namespace Drupal\ldbase_embargoes\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for files attached to embargoed nodes.
 */
class EmbargoedFileAccess extends EmbargoedAccessResult {

  /**
   * {@inheritdoc}
   */
  public static function entityType() {
    return 'file';
  }

  /**
   * {@inheritdoc}
   */
  public function isActivelyEmbargoed(EntityInterface $file, AccountInterface $user) {
    $state = parent::isActivelyEmbargoed($file, $user);
    $parent_nodes = $this->embargoes->getParentNidsOfFileEntity($file);
    $embargoes = $this->embargoes->getActiveEmbargoesByNids($parent_nodes, $user);
    if (!empty($embargoes)) {
      $state = AccessResult::forbidden();
      $state->addCacheableDependency($file);
      $state->addcacheableDependency($parent_nodes);
      $state->addCacheableDependency($user);
    }
    return $state;
  }

}
