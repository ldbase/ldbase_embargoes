<?php

namespace Drupal\ldbase_embargoes\Plugin\WebformHandler;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\message\Entity\Message;
use Drupal\node\Entity\Node;
use Drupal\webform\WebformInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates File Access Request message
 *
 * @WebformHandler(
 *   id = "request_file_access",
 *   label = @Translation("LDbase Request File Access"),
 *   category = @Translation("LDbase Embargoes"),
 *   description = @Translation("Creates File Access Request and saves message"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */

class RequestFileAccessHandler extends WebformHandlerBase {

  /**
   * The EntityTypeManager
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The LDbase message service.
   *
   * @var \Drupal\ldbase_handlers\LDbaseMessageService
   */
  protected $ldbaseMessageService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->ldbaseMessageService = $container->get('ldbase_handlers.message_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    //webform data
    $submission_array = $webform_submission->getData();
    $node_id = $submission_array['node_id'];
    $embargo_id = $submission_array['embargo_id'];
    $reason = $submission_array['reason_for_access_request'];
    //current user
    $current_user = $this->currentUser;
    $node = $this->entityTypeManager->getStorage('node')->load($node_id);
    $person = $this->entityTypeManager->getStorage('node')->loadByProperties(['field_drupal_account_id' => $current_user->id()]);
    $user_name = !empty($person) ? array_values($person)[0]->getTitle() : '';

    $confirmation_route = 'ldbase_embargoes.confirm_access_request';
    $confirmation_text = 'click here to grant access';
    $confirmation_url = Url::fromRoute($confirmation_route, array('node' => $node->uuid(), 'embargo' => $embargo_id, 'user' => $current_user->id()));
    $destination_param = UrlHelper::buildQuery(['destination' => $confirmation_url->toString()]);
    //$confirmation_link = Link::fromTextAndUrl(t($confirmation_text), $confirmation_url)->toString();
    $confirmation_link = $confirmation_url->setAbsolute()->toString() . '?' . $destination_param;
    $ldbase_message_service = $this->ldbaseMessageService;


    $ldbase_object = ucfirst($node->bundle());
    $ldbase_object_title = $node->getTitle();
    $project_administrators = $ldbase_message_service->getGroupUserIdsByRoles($node, ['project_group-administrator']);
    // get recipients
    $admin_emails = [];
    foreach ($project_administrators as $admin_id) {
      $admin = $this->entityTypeManager->getStorage('user')->load($admin_id);
      $admin_name = $ldbase_message_service->getPersonName($admin_id);
      $admin_email = $admin->mail->value;
      $admin_emails[] = $admin_name . ' <' . $admin_email .'>';
    }
    $admin_email_list = implode('; ', $admin_emails);
    $message_template = 'ldbase_embargoes_access_request';
    // create a new message from template for each project admin
    // Notify uses Message Author (uid) as "To" address
    foreach ($project_administrators as $admin) {
      $message = $this->entityTypeManager->getStorage('message')
        ->create(['template' => $message_template, 'uid' => $admin]);
      $message->set('field_from_user', $current_user->id());
      $message->set('field_to_user', $admin);
      $message->set('field_embargo', $embargo_id);
      $message->set('field_parent_node', $node_id);
      $message->setArguments([
        '@user_name' => $user_name,
        '@user_email' => $current_user->getDisplayName(),
        '@object_type' => $ldbase_object,
        '@object_title' => $ldbase_object_title,
        '@reasons_for_access' => $reason,
        '@confirm_link' => $confirmation_link,
        '@email_sent_to' => $admin_email_list,
      ]);

      $message->save();

      // send email notification
      $notifier = \Drupal::service('message_notify.sender');
      $notifier->send($message);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // redirect to node view
    $route_name = 'entity.node.canonical';
    $submission_array = $webform_submission->getData();
    $route_parameters = ['node' => $submission_array['node_id']];

    $this->messenger()->addStatus($this->t('Your request for access to this material has been sent.'));

    $form_state->setRedirect($route_name, $route_parameters);
  }

}
