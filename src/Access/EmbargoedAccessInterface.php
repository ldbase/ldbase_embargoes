<?php

namespace Drupal\ldbase_embargoes\Access;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Determine whether an item is embargoed and should be accessible.
 */
interface EmbargoedAccessInterface {

  /**
   * Asserts the asset in question is actively embargoed against the user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to determine embargo status for.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to determine embargo status for.
   *
   * @return bool
   *   TRUE or FALSE depending on if the given entity is actively embargoed
   *   against the current user.
   */
  public function isActivelyEmbargoed(EntityInterface $entity, AccountInterface $user);

  /**
   * Sets the message associated with embargoes for this entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to set the embargo message for.
   */
  public function setEmbargoMessage(EntityInterface $entity);

}
