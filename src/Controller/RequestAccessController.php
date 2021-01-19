<?php

namespace Drupal\ldbase_embargoes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Node\NodeInterface;
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
   * RequestAccess constructor
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *
   * @param \Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface $embargoes
   * An embargoes service.
   */
  public function __construct(EmbargoesEmbargoesServiceInterface $embargoes, EntityTypeManagerInterface $entityTypeManager) {
    $this->embargoes = $embargoes;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ldbase_embargoes.embargoes'),
      $container->get('entity_type.manager')
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
    if (!\Drupal::currentUser()->isAnonymous()) {
      $node_id = $node->id();
      $embargo_id = $this->embargoes->getAllEmbargoesByNids([$node_id]);

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
    else {
      return $this->redirect('user.login');
    }
  }

}
