<?php

namespace Drupal\ldbase_embargoes\Plugin\Block;

use Drupal\node\NodeInterface;
use Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Embargo Policies" block.
 *
 * @Block(
 *   id="embargoes_embargo_policies_block",
 *   admin_label = @Translation("Embargo Details"),
 *   category = @Translation("LDbase Embargoes")
 * )
 */
class EmbargoesEmbargoPoliciesBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * An entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('ldbase_embargoes.embargoes'),
      $container->get('entity_type.manager'));
  }

  /**
   * Constructs an embargoes policies block.
   *
   * @param array $configuration
   *   Block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $route_match
   *   A route matching interface.
   * @param \Drupal\ldbase_embargoes\EmbargoesEmbargoesServiceInterface $embargoes
   *   An embargoes management service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   An entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ResettableStackedRouteMatchInterface $route_match, EmbargoesEmbargoesServiceInterface $embargoes, EntityTypeManagerInterface $entity_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->embargoes = $embargoes;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      $embargoes = $this->embargoes->getCurrentEmbargoesByNids([$node->id()]);
      if (count($embargoes) > 0) {
        $t = $this->getStringTranslation();
        $embargoes_info = [];
        $cache_tags = [
          "node:{$node->id()}",
        ];
        $embargoes_count = $t->formatPlural(count($embargoes),
          'This resource has 1 embargo:',
          'This resource has @count embargoes:');

        foreach ($embargoes as $embargo_id) {
          $embargo = $this->entityManager->getStorage('node')->load($embargo_id);
          $embargo_info = [];
          $embargo_expiry = $embargo->get('field_expiration_date')->value;
          $embargo_expiration_type = empty($embargo_expiry) ? 0 : 1;
          // Expiration string.
          if (!$embargo_expiration_type) {
            $embargo_info['expiration'] = $t->translate('Duration: Indefinite');
          }
          else {
            $embargo_info['expiration'] = $t->translate('Duration: Until @duration', [
              '@duration' => $embargo_expiry,
            ]);
          }
          // Embargo type string.
          if (!$embargo->get('field_embargo_type')->value) {
            $embargo_info['type'] = $t->translate('Disallow Access To: Resource Files');
          }
          else {
            $embargo_info['type'] = $t->translate('Disallow Access To: Resource');
          }
          $embargoes_info[] = $embargo_info;

          $cache_tags[] = "ldbase_embargoes_embargo_entity:{$embargo->id()}";
        }

        return [
          '#theme' => 'ldbase_embargoes_policies',
          '#count' => $embargoes_count,
          '#embargoes_info' => $embargoes_info,
          '#cache' => [
            'tags' => $cache_tags,
          ],
        ];
      }
    }

    return [];
  }

  public function getCacheTags() {
    //With this when your node change your block will rebuild
    if ($node = $this->routeMatch->getParameter('node')) {
      return Cache::mergeTags(parent::getCacheTags(), array('node:' . $node->id()));
    } else {
      return parent::getCacheTags();
    }
  }

  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), array('route'));
  }

  /*public function getCacheMaxAge() {
    return 0;
  }*/

}
