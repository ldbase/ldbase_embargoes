<?php

namespace Drupal\ldbase_embargoes;

use Drupal\ldbase_embargoes\Entity\EmbargoesEmbargoEntityInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface EmbargoesEmbargoesServiceInterface.
 */
interface EmbargoesEmbargoesServiceInterface {

  /**
   * Gets a list of all embargoes that apply to the given node IDs.
   *
   * @param int[] $nids
   *   The list of node IDs to get embargoes for.
   *
   * @return int[]
   *   An array of embargo entity IDs for the given nodes.
   */
  public function getAllEmbargoesByNids(array $nids);

  /**
   * Gets a list of unexpired embargoes that apply to the given node IDs.
   *
   * @param int[] $nids
   *   The list of node IDs to get active embargoes for.
   *
   * @return int[]
   *   An array of active embargo entity IDs for the given nodes.
   */
  public function getCurrentEmbargoesByNids(array $nids);

  /**
   * Gets embargoes for the given node IDs that apply to the given user.
   *
   * @param int[] $nids
   *   The list of node IDs to query against.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity to test embargoes against.
   *
   * @return int[]
   *   An associative array mapping any embargoes from the given node IDs that
   *   apply to the user to that same node ID.
   */
  public function getActiveEmbargoesByNids(array $nids, AccountInterface $user);

  /**
   * Gets node-level embargoes for the given node IDs that apply to the user.
   *
   * @param int[] $nids
   *   The list of node IDs to query against.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity to test embargoes against.
   *
   * @return int[]
   *   An associative array mapping any embargoes from the given node IDs that
   *   apply to the user to that same node ID, filtered to only include
   *   embargoes that apply to the node itself.
   */
  public function getActiveNodeEmbargoesByNids(array $nids, AccountInterface $user);

  /**
   * Determines whether a given $user is in the exemption list for an embargo.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity to test against.
   * @param int $embargo_id
   *   The embargo to check the list of exempt users for.
   *
   * @return bool
   *   TRUE or FALSE, depending on whether or not the given user is in the list
   *   of exempt users for the given embargo.
   */
  public function isUserInExemptUsers(AccountInterface $user, $embargo_id);

  /**
   * Determines whether a given $user is an admin for the Group.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity to test against.
   * @param int $embargo_id
   *   The embargo to check the list of exempt users for.
   *
   */
  public function isUserGroupAdministrator(AccountInterface $user, $embargo_id);

  /**
   * Determines whether a given $user is an editor for the Group.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user entity to test against.
   * @param int $embargo_id
   *   The embargo to check the list of exempt users for.
   *
   */
  public function isUserGroupEditor(AccountInterface $user, $embargo_id);

  /**
   * Gets a list of entity_reference fields that target media.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A list of entity_reference fields that target media entities.
   */
  public function getNodeMediaReferenceFields();

  /**
   * Gets a list of nodes that are the parent of the given media ID.
   *
   * @param int $mid
   *   The ID of the media entity to get parents for.
   *
   * @return int[]
   *   A list of node IDs that are parents of the given media.
   */
  public function getMediaParentNids($mid);

  /**
   * Gets a list of nodes that are the parent of the given paragraph ID.
   *
   * @param int $pid
   *   The ID of the paragraph entity to get parents for.
   *
   * @return int[]
   *   A list of node IDs that are parents of the given paragraph.
   */
  public function getParagraphParentNids($pid);

  /**
   * Gets a list of parent node IDs for the given file entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity to get parent node IDs for.
   *
   * @return int[]
   *   An array of node IDs that are parents of the given file.
   */
  public function getParentNidsOfFileEntity(FileInterface $file);

}
