<?php

namespace Drupal\ldbase_embargoes;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\FileInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Class EmbargoesEmbargoesService.
 */
class EmbargoesEmbargoesService implements EmbargoesEmbargoesServiceInterface {

  /**
   * An entity type manager interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * An entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new EmbargoesEmbargoesService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   An entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   An entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, EntityFieldManagerInterface $field_manager, ModuleHandlerInterface $module_handler) {
    $this->entityManager = $entity_manager;
    $this->fieldManager = $field_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllEmbargoesByNids(array $nids) {
    $all_embargoes = [];
    foreach ($nids as $nid) {
      $node_embargoes = $this->entityManager
        ->getStorage('node')
        ->getQuery()
        ->condition('field_embargoed_node', $nid)
        ->execute();
      $all_embargoes = array_merge($all_embargoes, $node_embargoes);
    }
    return $all_embargoes;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentEmbargoesByNids(array $nids) {
    $current_embargoes = [];
    $embargoes = $this->getAllEmbargoesByNids($nids);
    foreach ($embargoes as $embargo_id) {
      $embargo = $this->entityManager
        ->getStorage('node')
        ->load($embargo_id);
      if ($embargo->field_expiration_type->value == 0) {
        $current_embargoes[$embargo_id] = $embargo_id;
      }
      else {
        $now = time();
        $expiry = strtotime($embargo->field_expiration_date->value);
        if ($expiry > $now) {
          $current_embargoes[$embargo_id] = $embargo_id;
        }
      }
    }
    return $current_embargoes;
  }

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
   *   apply to the user to that same ID. An embargo does not apply to the user
   *   if any of the following conditions are true:
   *   - The user is in the list of exempt users for the embargo
   *   - The user has the 'bypass embargoes restrictions' permission
   *   - The user is a Project Group administrator
   *   - The user is a Project Group editor
   */
  public function getActiveEmbargoesByNids(array $nids, AccountInterface $user) {
    $active_embargoes = [];
    $embargoes = $this->getCurrentEmbargoesByNids($nids);
    foreach ($embargoes as $embargo_id) {
      $user_is_exempt = $this->isUserInExemptUsers($user, $embargo_id);
      $role_is_exempt = $user->hasPermission('bypass embargoes restrictions');
      $user_is_group_admin = $this->isUserGroupAdministrator($user, $embargo_id);
      $user_is_group_editor = $this->isUserGroupEditor($user, $embargo_id);
      if (!$user_is_exempt && !$role_is_exempt && !$user_is_group_admin && !$user_is_group_editor) {
        $active_embargoes[$embargo_id] = $embargo_id;
      }
    }
    return $active_embargoes;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveNodeEmbargoesByNids(array $nids, AccountInterface $user) {
    $active_node_embargoes = [];
    $embargoes = $this->getActiveEmbargoesByNids($nids, $user);
    foreach ($embargoes as $embargo_id) {
      $embargo = $this->entityManager
        ->getStorage('node')
        ->load($embargo_id);
      if ($embargo->field_embargo_type->value == 1) {
        $active_node_embargoes[$embargo_id] = $embargo_id;
      }
    }
    return $active_node_embargoes;
  }

  /**
   * {@inheritdoc}
   */
  public function isUserInExemptUsers(AccountInterface $user, $embargo_id) {
    $embargo = $this->entityManager
      ->getStorage('node')
      ->load($embargo_id);
    $exempt_users = $embargo->field_exempt_users;
    if (is_null($exempt_users)) {
      $user_is_exempt = FALSE;
    }
    else {
      $exempt_users_flattened = [];
      foreach ($exempt_users as $exempt_user) {
        $exempt_users_flattened[] = $exempt_user->target_id;
      }
      if (in_array($user->id(), $exempt_users_flattened)) {
        $user_is_exempt = TRUE;
      }
      else {
        $user_is_exempt = FALSE;
      }
    }
    return $user_is_exempt;
  }

  /**
   * {@inheritdoc}
   */
  public function isUserGroupAdministrator(AccountInterface $user, $embargo_id) {
    return $this->hasGroupRole($user, $embargo_id, 'project_group-administrator');
  }

  /**
   * {@inheritdoc}
   */
  public function isUserGroupEditor(AccountInterface $user, $embargo_id) {
    return $this->hasGroupRole($user, $embargo_id, 'project_group-editor');
  }

  /**
   * {@inheritdoc}
   */
  private function hasGroupRole(AccountInterface $user, $embargo_id, $group_role) {
    if (empty($group_role)) {
      return false;
    }
    if ($this->moduleHandler->moduleExists('group')) {
      $embargo = $this->entityManager
        ->getStorage('node')
        ->load($embargo_id);
      $embargoed_node = $this->entityManager
        ->getStorage('node')
        ->load($embargo->field_embargoed_node->target_id);
      $group_content = $this->entityManager
        ->getStorage('group_content')
        ->loadByEntity($embargoed_node);
      $ldbase_group = array_pop($group_content);
      $group = $ldbase_group->getGroup();
      $group_member = $group->getMember($user);
      $user_has_role = FALSE;
      if ($group_member) {
        $group_member_roles = $group_member->getRoles();
        if (in_array($group_role, array_keys($group_member_roles))) {
          $user_has_role = true;
        }
      }
      return $user_has_role;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeMediaReferenceFields() {
    $entity_fields = array_keys($this->fieldManager->getFieldMapByFieldType('entity_reference')['node']);
    $media_fields = [];
    foreach ($entity_fields as $field) {
      if (strpos($field, 'field_') === 0) {
        $field_data = FieldStorageConfig::loadByName('node', $field);
        if ($field_data->getSetting('target_type') == 'media') {
          $media_fields[] = $field;
        }
      }
    }
    return $media_fields;
  }

  /**
   * Gets a list of nodes that are the parent of the given media ID.
   *
   * @param int $mid
   *   The ID of the media entity to get parents for.
   *
   * @return int[]
   *   A list of node IDs that are parents of the given media. A node is a
   *   parent of the given media if either:
   *   - The media implements and has one or more valid values for
   *     field_media_of, or
   *   - Any node implements an entity_reference field that targets media, and
   *     contains a value targeting the given $mid
   */
  public function getMediaParentNids($mid) {
    $media_entity = $this->entityManager
      ->getStorage('media')
      ->load($mid);
    if ($media_entity && $media_entity->hasField('field_media_of')) {
      $nid = $media_entity->get('field_media_of')->getString();
      $nids = [$nid];
    }
    else {
      $media_fields = $this->getNodeMediaReferenceFields();
      $query = $this->entityManager
        ->getStorage('node')
        ->getQuery();
      $group = $query->orConditionGroup();
      foreach ($media_fields as $field) {
        $group->condition($field, $mid);
      }
      $result = $query->condition($group)->execute();
      $nids = array_values($result);
    }
    return $nids;
  }

  public function getParagraphParentNids($pid) {
    $paragraph_entity = $this->entityManager
      ->getStorage('paragraph')
      ->load($pid);
    if ($paragraph_entity) {
      $nid = $paragraph_entity->parent_id->value;
      $nids = [$nid];
    }
    else {
      $nids = [];
    }
    return $nids;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentNidsOfFileEntity(FileInterface $file) {
    $relationships = NestedArray::mergeDeep(
      file_get_file_references($file),
      file_get_file_references($file, NULL, EntityStorageInterface::FIELD_LOAD_REVISION, 'image'));
    if (!$relationships) {
      $nids = [];
    }
    else {
      foreach ($relationships as $relationship) {
        if (!$relationship) {
          $nids = [];
        }
        else {
          foreach ($relationship as $key => $value) {
            switch ($key) {
              case 'node':
                $nids = [array_keys($value)[0]];
                break;
              case 'paragraph':
                $pid = array_keys($value)[0];
                $nids = $this->getParagraphParentNids($pid);
                break;
              case 'media':
                $mid = array_keys($value)[0];
                $nids = $this->getMediaParentNids($mid);
                break;
            }
          }
        }
      }
    }
    return $nids;
  }

}
