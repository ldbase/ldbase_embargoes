<?php

namespace Drupal\ldbase_embargoes\Access;

use Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use InvalidArgumentException;

/**
 * Base implementation of embargoed access.
 */
abstract class EmbargoedAccessResult implements EmbargoedAccessInterface {

  /**
   * An embargoes service.
   *
   * @var \Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface
   */
  protected $embargoes;

  /**
   * The request object.
   *
   * @var Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * An entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A Drupal messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * String translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translator;

  /**
   * Constructor for access control managers.
   *
   * @param \Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface $embargoes
   *   An embargoes service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request being made to check access against.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   An entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   A Drupal messenger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translator
   *   A string translation manager.
   */
  public function __construct(EmbargoesEmbargoesServiceInterface $embargoes, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $translator) {
    $this->embargoes = $embargoes;
    $this->request = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->translator = $translator;
  }

  /**
   * Return the type of entity this should apply to.
   *
   * @return string
   *   The entity type this access control should apply to.
   */
  abstract public static function entityType();

  /**
   * {@inheritdoc}
   */
  public function isActivelyEmbargoed(EntityInterface $entity, AccountInterface $user) {
    $entity_type = $entity->getEntityType()->id();
    $expected = static::entityType();
    if ($entity_type !== $expected) {
      throw new InvalidArgumentException($this->translator->translate('Attempting to check embargoed access status for an entity of type %type (expected: %expected)', [
        '%type' => $entity_type,
        '%expected' => $expected,
      ]));
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function setEmbargoMessage(EntityInterface $entity) {
    $embargoes = $this->embargoes->getCurrentEmbargoesByNids([$entity->id()]);
    if ($this->shouldSetEmbargoMessage() && !empty($embargoes)) {
      // Warnings to pop.
      $messages = [
        $this->translator->formatPlural(count($embargoes), 'This resource is under 1 embargo', 'This resource is under @count embargoes'),
      ];
      // Pop additional warnings per embargo.
      foreach ($embargoes as $embargo_id) {
        $embargo = $this->entityTypeManager
          ->getStorage('node')
          ->load($embargo_id);
        if ($embargo) {
          // Custom built message from three conditions: are nodes or files
          // embargoed, and does it expire?
          $type = $embargo->field_embargo_type->value;
          $expiration = $embargo->field_expiration_type->value;
          $expiration_date = $expiration ? $embargo->field_expiration_date->value : '';
          $args = [
            '%date' => $expiration_date,
          ];

          // Determine a message to set.
          if (!$type && !$expiration) {
            $messages[] = $this->translator->translate('- Access to all associated files of this resource is restricted indefinitely.');
          }
          elseif (!$type && $expiration) {
            $messages[] = $this->translator->translate('- Access to all associated files of this resource is restricted until %date.', $args);
          }
          elseif ($type && !$expiration) {
            $messages[] = $this->translator->translate('- Access to this resource and all associated resources is restricted indefinitely.');
          }
          elseif ($type && $expiration) {
            $messages[] = $this->translator->translate('- Access to this resource and all associated resources is restricted until %date.', $args);
          }
          else {
            $messages[] = $this->translator->translate('- Access to this resource and all associated resources is restricted until %date.', $args);
          }

          // determine if current user is exempt
          $user_is_exempt = false;
          $current_user = \Drupal::currentUser();
          $exempt_users = $embargo->get('field_exempt_users')->getValue();
          foreach ($exempt_users as $user) {
            if ($user['target_id'] == $current_user->id()) {
              $messages[] = $this->translator->translate('- You have been granted an access exemption to this resource.');
            }
          }
          // is user group admin?
          if ($this->embargoes->isUserGroupAdministrator($current_user, $embargo_id)) {
            $messages[] = $this->translator->translate('- You have access to this resource as a Project Administrator.');
          }
          // is user project editor?
          if ($this->embargoes->isUserGroupEditor($current_user, $embargo_id)) {
            $messages[] = $this->translator->translate('- You have access to this resource as a Project Editor.');
          }
        }
      }
      foreach ($messages as $message) {
        $this->messenger->addWarning($message);
      }
    }
  }

  /**
   * Helper to determine if the embargo message should be set.
   *
   * @return bool
   *   TRUE or FALSE depending on whether an embargo message should be set.
   */
  protected function shouldSetEmbargoMessage() {
    return true;  // until config built
  }

}
