<?php

namespace Drupal\ldbase_embargoes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Node\NodeInterface;
use Drupal\user_email_verification\UserEmailVerification;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RequestAccessController extends ControllerBase {

  /**
   * An embargoes service.
   *
   * @var \Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface
   */
  protected $embargoes;

  /**
   * An entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The User Email Verification service.
   *
   * @var \Drupal\user_email_verification\UserEmailVerification;
   */
  protected $userEmailVerification;

  /**
   * RequestAccess constructor
   * @param \Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface $embargoes
   *  An embargoes service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *    The EntityTypeManager
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The Current User
   *
   * @param \Drupal\user_email_verification\UserEmailVerification $userEmailVerification
   *  User Email Verification Service
   */
  public function __construct(EmbargoesEmbargoesServiceInterface $embargoes, EntityTypeManagerInterface $entityTypeManager, AccountInterface $currentUser, UserEmailVerification $userEmailVerification) {
    $this->embargoes = $embargoes;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->userEmailVerification = $userEmailVerification;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ldbase_embargoes.embargoes'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('user_email_verification.service')
    );
  }

  /**
   * Gets title for Embargo Access Request form page
   *
   * @param \Drupal\Node\NodeInterface $node
   */
  public function getRequestFormTitle(NodeInterface $node) {
    $node_type = ucFirst($node->getType());
    $node_title = $node->getTitle();
    return "Request Access to Embargoed {$node_type}: {$node_title}";
  }

  public function requestAccess(NodeInterface $node) {
    if (!$this->currentUser->isAnonymous()) {
      $node_id = $node->id();
      $embargo_id = $this->embargoes->getAllEmbargoesByNids([$node_id]);

      // Has the user verified their email address?
      $uid = $this->currentUser->id();
      if ($this->userEmailVerification->isVerificationNeeded($uid)) {
        // if not user, redirect to Person view with error message
        $redirect_message = $this->t("You must verify your email address to contact others. <a href='/user/user-email-verification'>Resend verification link</a>");
        $this->messenger()->addError($redirect_message);

        return $this->redirect('entity.node.canonical', ['node' => $node_id]);
      }
      else {
        $values = [
          'data' => [
            'node_id' => $node_id,
            'embargo_id' => $embargo_id[0],
          ]
        ];

        $operation = 'add';
        // get webform and load values
        $webform = $this->entityTypeManager->getStorage('webform')->load('request_file_access');
        $webform = $webform->getSubmissionForm($values, $operation);
        return $webform;
      }
    }
    else {
      return $this->redirect('user.login');
    }
  }

}
