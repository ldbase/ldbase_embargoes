<?php

namespace Drupal\ldbase_embargoes\Form;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;

/**
 * Defines form to confirm removal of LDbase user from the list of Exempt Users
 */
class ConfirmExemptionDeleteForm extends ConfirmFormBase {

  /**
   * UUID of embargo
   * @param Drupal\node\Entity\Node $embargo
   */
  protected $embargo;

  /**
   * user id of user to be removed
   * @param Drupal\user\Entity\User $user
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Node $embargo = NULL, User $user = NULL) {
    $this->embargo = $embargo;
    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'confirm_embargo_access_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $embargo = $this->embargo;
    $node_id = $embargo->field_embargoed_node->target_id;
    $route = 'entity.node.canonical';
    $url_parameters = [
      'node' => $node_id,
    ];
    $url = Url::fromRoute($route, $url_parameters);
    return $url;
  }


  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to remove access?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $embargo = $this->embargo;
    $node_id = $embargo->field_embargoed_node->target_id;
    $embargoed_node = $node_storage->load($node_id);
    $type = \Drupal::service('ldbase.object_service')->isLdbaseCodebook($embargoed_node->uuid()) ? 'codebook' : $embargoed_node->bundle();
    $description = "<div class='delete-content-confirmation'>" .
      '<p>' . t('If you confirm, the user will no longer be able to access the embargoed material for %type: %title.', [
        '%type' => ucfirst($type),
        '%title' => $embargoed_node->getTitle(),
      ]) . '</p>' .
      '</div>';
    return Markup::create($description);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $user_id = $this->user->uid->value;
    $embargo = $this->embargo;
    $exempt_users = $embargo->field_exempt_users->getValue();
    foreach ($exempt_users as $idx => $value) {
      if ($value['target_id'] == $user_id) {
        unset($exempt_users[$idx]);
      }
    }
    $embargo->set('field_exempt_users', $exempt_users);
    $embargo->save();
    $node_id = $embargo->field_embargoed_node->target_id;
    $embargoed_node = $node_storage->load($node_id);
    Cache::invalidateTags($embargoed_node->getCacheTags());
    $this->messenger()->addStatus($this->t('Access to the embargoed material has been removed.'));
  }
}
