<?php

namespace Drupal\node_revision_delete;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Service to delete old revisions on entity types.
 *
 * @package Drupal\node_revision_delete
 */
class RevisionCleanup implements RevisionCleanupInterface {

  use StringTranslationTrait;

  /**
   * Database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Database logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Config entity with cleanup settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * RevisionCleanup constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   */
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('node_revision_delete');
    $this->config = $config_factory->get('node_revision_delete.settings');
  }

  /**
   * {@inheritDoc}
   */
  public function deleteRevision($nid, $vid) {
    // Make sure the given revision id should be deleted.
    if ($this->shouldDeleteRevision($nid, $vid)) {
      $this->entityTypeManager->getStorage('node')
        ->deleteRevision($vid);
    }
  }

  /**
   * Should the provided revision id on the node be deleted.
   *
   * @param int $nid
   *   Node ID.
   * @param int $vid
   *   Revision ID.
   *
   * @return bool
   *   True if the revision can be deleted.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function shouldDeleteRevision($nid, $vid) {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return FALSE;
    }

    $keep = $this->config->get("node_types.{$node->getType()}.keep");
    $age = $this->config->get("node_types.{$node->getType()}.age");
    $revision_ids = $this->getRevisionIds($node->getType(), $keep, $age, $node->id());

    return isset($revision_ids[$nid][$vid]);
  }

  /**
   * Get all revision ids for the entity type that are not CURRENT revisions.
   *
   * @param string $bundle
   *   Node bundle id.
   * @param int $keep
   *   Number of revisions to keep.
   * @param string $changed
   *   Relative time string.
   * @param int $nid
   *   Node id.
   *
   * @return array
   *   Keyed array of entity ids and its revision ids.
   */
  protected function getRevisionIds($bundle, $keep = 0, $changed = '', $nid = NULL) {
    // Query on the revision table for the entity type.
    $query = $this->database->select('node_field_revision', 'r')
      ->fields('r', ['nid', 'vid'])
      ->fields('b', ['type']);

    // Join the base table so that we can exclude the current revision.
    $query->join('node_field_data', 'b', "b.nid = r.nid");
    $query->orderBy('nid', 'ASC');
    $query->orderBy('vid', 'DESC');

    // Exclude the current revision of the entity.
    $query->where("r.vid != b.vid");
    $query->where("b.type = '$bundle'");

    if ($changed) {
      $timestamp = strtotime("-$changed");
      $query->where("r.changed <= $timestamp");
    }

    if ($nid) {
      $query->where("r.nid = $nid");
    }

    $result = $query->execute();
    $revision_ids = [];
    while ($item = $result->fetchAssoc()) {
      $revision_ids[$item['nid']][$item['vid']] = $item;
    }

    if ($keep - 1 > 0) {
      // Slice the revision ids to keep only the number of revisions we want.
      foreach ($revision_ids as &$ids) {
        $ids = array_slice($ids, 0, -($keep - 1), TRUE);
      }
    }

    return array_filter($revision_ids);
  }

  /**
   * {@inheritDoc}
   */
  public function queueRevisionsForNode(NodeInterface $node) {
    if (empty($node->id()) || empty($this->config->get("node_types.{$node->getType()}"))) {
      return;
    }

    $keep = $this->config->get("node_types.{$node->getType()}.keep");
    $age = $this->config->get("node_types.{$node->getType()}.age");
    $revision_ids = $this->getRevisionIds($node->getType(), $keep, $age, $node->id());

    if (isset($revision_ids[$node->id()])) {
      foreach (array_keys($revision_ids[$node->id()]) as $revision_id) {
        self::queueRevision($node->id(), $revision_id);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function queueAllRevisions() {
    $this->database->delete('queue')
      ->condition('name', 'node_revision_delete')
      ->execute();

    foreach ($this->config->get('node_types') as $node_type => $settings) {
      foreach ($this->getRevisionIds($node_type, $settings['keep'], $settings['age']) as $node_id => $revisions) {
        foreach (array_keys($revisions) as $revision_id) {
          self::queueRevision($node_id, $revision_id);
        }
      }
    }
  }

  /**
   * Create a cron queue item from the given data.
   *
   * @param int $nid
   *   Node ID.
   * @param int $vid
   *   Revision ID.
   */
  protected static function queueRevision($nid, $vid) {
    /** @var \Drupal\Core\Queue\QueueFactory $queue_factory */
    $queue_factory = \Drupal::service('queue');
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queue_factory->get('node_revision_delete');
    $item = new \stdClass();
    $item->nid = $nid;
    $item->vid = $vid;
    $queue->createItem($item);
  }

}
