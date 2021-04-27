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
 * Defines form to confirm and grant access to an embargo
 */
class ConfirmAccessRequest extends ConfirmFormBase {

  /**
   * UUID of embargoed node
   * @param Drupal\node\Entity\Node $node
   */
  protected $node;

  /**
   * nid of embargo
   * @param Drupal\node\Entity\Node $embargo
   */
  protected $embargo;

  /**
   * user id of requesting user
   * @param Drupal\user\Entity\User $user
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Node $node = NULL, Node $embargo = NULL, User $user = NULL) {
    $this->node = $node;
    $this->embargo = $embargo;
    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return 'confirm_embargo_access_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $current_user = \Drupal::currentUser();
    $route = 'entity.user.canonical';
    $url_parameters = [
      'user' => $current_user->id(),
    ];
    $url = Url::fromRoute($route, $url_parameters);
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to grant access to this embargoed material?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $node = $this->node;
    $user = $this->user;
    $etm = \Drupal::entityTypeManager();
    $material = ucFirst($node->bundle()) . ': ' . $node->getTitle();
    $person = $etm->getStorage('node')->loadByProperties(['field_drupal_account_id' => $user->id()]);
    $user_name = !empty($person) ? array_values($person)[0]->getTitle() : $user->getTitle();

    $description = "<div class='embargo-access-confirmation'>" .
      '<p>' . t('If you confirm, then %user will have access to %material.', [
        '%user' => $user_name,
        '%material' => $material,
      ]) . '</p>' .
      '</div>';
    return Markup::create($description);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $this->node;
    $user = $this->user;
    $embargo = $this->embargo;
    $exempt_users = $embargo->get('field_exempt_users')->getValue();
    $user_already_exempt = false;
    foreach ($exempt_users as $exempt_user) {
      if ($exempt_user['target_id'] == $user->id()) {
        $user_already_exempt = true;
      }
    }
    if (!$user_already_exempt) {
      $embargo->field_exempt_users[] = ['target_id' => $user->id()];
      $embargo->save();
      Cache::invalidateTags($node->getCacheTags());
      $this->messenger()->addStatus($this->t('The user has been given access to the embargoed material.'));
    }
    else {
      $this->messenger()->addStatus($this->t('The user already has access to the embargoed material.'));
    }
    $route = 'entity.node.canonical';
    $url_parameters = [
      'node' => $node->id(),
    ];
    $url = Url::fromRoute($route, $url_parameters);
    $form_state->setRedirectUrl($url);

  }

}
