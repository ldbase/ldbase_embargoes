<?php

namespace Drupal\ldbase_embargoes\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control for embargoed media.
 */
class EmbargoedMediaAccess extends EmbargoedAccessResult {

  /**
   * {@inheritdoc}
   */
  public static function entityType() {
    return 'media';
  }

  /**
   * {@inheritdoc}
   */
  public function isActivelyEmbargoed(EntityInterface $media, AccountInterface $user) {
    $state = parent::isActivelyEmbargoed($media, $user);
    $parent_nodes = $this->embargoes->getMediaParentNids($media->id());
    $embargoes = $this->embargoes->getActiveNodeEmbargoesByNids($parent_nodes, $user);
    if (!empty($embargoes)) {
      $state = AccessResult::forbidden();
      $state->addCacheableDependency($media);
      $state->addCacheableDependency($user);
    }
    return $state;
  }

}
