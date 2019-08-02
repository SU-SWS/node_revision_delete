<?php

namespace Drupal\node_revision_delete\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node_revision_delete\RevisionCleanupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodeRevisionDelete
 *
 * @QueueWorker(
 *   id = "node_revision_delete",
 *   title = @Translation("Node Revision Delete"),
 *   cron = {"time" = 60}
 * )
 */
class NodeRevisionDelete extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Revision cleanup service.
   *
   * @var \Drupal\node_revision_delete\RevisionCleanupInterface
   */
  protected $revisionCleanup;

  /**
   * {@inheritDoc}}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('node_revision_delete')
    );
  }

  /**
   * {@inheritDoc}}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RevisionCleanupInterface $revision_cleanup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->revisionCleanup = $revision_cleanup;
  }

  /**
   * {@inheritDoc}}
   */
  public function processItem($item) {
    $this->revisionCleanup->deleteRevision($item->nid, $item->vid);
  }

}
