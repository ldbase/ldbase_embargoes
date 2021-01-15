<?php

namespace Drupal\ldbase_embargoes\Plugin\Block;

use Drupal\node\NodeInterface;
use Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Embargo Notifications" block.
 *
 * @Block(
 *   id="ldbase_embargoes_embargo_notification_block",
 *   admin_label = @Translation("Embargo Detail with Access Request"),
 *   category = @Translation("LDbase Embargoes")
 * )
 */
class EmbargoesEmbargoNotificationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * An entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * A route matching interface.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $routeMatch;

  /**
   * An embargoes service.
   *
   * @var \Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface
   */
  protected $embargoes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('config.factory'),
      $container->get('ldbase_embargoes.embargoes'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Construct embargo notification block.
   *
   * @param array $configuration
   *   Block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $route_match
   *   A route matching interface.
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A configuration factory interface.
   * @param \Drupal\embargoes\EmbargoesEmbargoesServiceInterface $embargoes
   *   An embargoes management service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   An entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ResettableStackedRouteMatchInterface $route_match, ConfigFactoryInterface $config_factory, EmbargoesEmbargoesServiceInterface $embargoes, EntityTypeManagerInterface $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = $entity_manager;
    $this->embargoes = $embargoes;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      $embargoes = $this->embargoes->getCurrentEmbargoesByNids([$node->id()]);
      $num_embargoes = count($embargoes);

      if ($num_embargoes > 0) {
        $t = $this->getStringTranslation();
        $embargoes_info = [];
        $cache_tags = [
          "node:{$node->id()}",
          "extensions",
          "env",
        ];
        $embargoes_count = $t->formatPlural(
          $num_embargoes,
          'This resource is under 1 embargo:',
          'This resource is under @count embargoes:'
        );

        $contact_message = "";
        foreach ($embargoes as $embargo_id) {

          $embargo = $this->entityManager->getStorage('node')->load($embargo_id);
          $embargo_info = [];
          $embargo_expiry = $embargo->get('field_expiration_date')->value;
          $embargo_expiration_type = empty($embargo_expiry) ? 0 : 1;

          // Expiration string.
          if (!$embargo_expiration_type) {
            $embargo_info['expiration'] = $t->translate('Duration: Indefinite');
            $embargo_info['has_duration'] = FALSE;
          }
          else {
            $embargo_info['expiration'] = $t->translate('Duration: Until @duration', [
              '@duration' => $embargo_expiry,
            ]);
            $embargo_info['has_duration'] = TRUE;
          }

          // Embargo type string, including a message for the given type.
          if (!$embargo->get('field_embargo_type')->value) {
            $embargo_info['type'] = 'Files';
            $embargo_info['type_message'] = $t->translate('Access to all associated files of this resource is restricted');
          }
          else {
            $embargo_info['type'] = 'Node';
            $embargo_info['type_message'] = $t->translate('Access to this resource and all associated files is restricted');
          }

          // Determine if given user is exempt or not. If not, prepare a message
          // the user can use to request access.
          if (\Drupal::currentUser()->isAuthenticated()) {
            $exempt_users = $embargo->get('field_exempt_users')->getValue();
            $embargo_info['user_exempt'] = FALSE;
            foreach ($exempt_users as $user) {
              if ($user['target_id'] == \Drupal::currentUser()->id()) {
                $embargo_info['user_exempt'] = TRUE;
              }
            }
            if ($this->embargoes->isUserGroupAdministrator(\Drupal::currentUser(), $embargo_id)) {
              $embargo_info['user_exempt'] = TRUE;
            }
            if (!$embargo_info['user_exempt']) {
              $request_access_route = "ldbase_embargoes.request_{$node->getType()}_embargo_access";
              $link_text = "Request Access";
              $url = Url::fromRoute($request_access_route, array('node' => $node->uuid()));
              $link = Link::fromTextAndUrl(t($link_text), $url)->toRenderable();
              $link['#attributes'] = ['class' => ['ldbase-button']];
              $contact_message = $link;
            }
          }
          else {
            $contact_message = '';
          }


          $embargo_info['dom_id'] = Html::getUniqueId('ldbase_embargo_notification');
          $embargoes_info[] = $embargo_info;

          array_push(
            $cache_tags,
            "node.{$node->id()}.embargoes.{$embargo->id()}"
          );

        }

        return [
          '#theme' => 'ldbase_embargoes_notifications',
          '#count' => $embargoes_count,
          '#message' => $contact_message,
          '#embargoes_info' => $embargoes_info,
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // When the given node changes (route), the block should rebuild.
    if ($node = \Drupal::routeMatch()->getParameter('node')) {
      return Cache::mergeTags(
        parent::getCacheTags(),
        array('node:' . $node->id())
      );
    }

    // Return default tags, if not on a node page.
    return parent::getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Ensure that with every new node/route, this block will be rebuilt.
    return Cache::mergeContexts(parent::getCacheContexts(), array('route'));
  }

}
