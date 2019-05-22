<?php

namespace Drupal\node_revision_delete;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
  public function deleteRevisions() {
    $deleted_count = 0;
    foreach ($this->config->get('node_types') as $bundle => $settings) {
      $revision_ids = [];
      switch ($settings['method']) {
        case self::NODE_REVISION_DELETE_COUNT:
          $revision_ids = $this->getRevisionIds($bundle, $settings['keep']);
          break;
        case self::NODE_REVISION_DELETE_AGE:
          $revision_ids = $this->getRevisionIds($bundle, $settings['keep'], $settings['changed']);
          break;
      }

      $deleted_count = $this->deleteEntityRevisions($revision_ids);
      if ($deleted_count >= $this->getCronLimit()) {
        break;
      }
    }

    if ($deleted_count) {
      $this->logger->info('Deleted @count node revisions', ['@count' => $deleted_count]);
    }
  }

  /**
   * Get the number of revisions to limit cron to delete.
   *
   * @return int
   *   Total number to delete.
   */
  protected function getCronLimit() {
    return $this->config->get('cron_limit') ?: 50;
  }

  /**
   * Delete all old revisions of the given entity type.
   *
   * @param array $revision_ids
   *   Entity type id.
   *
   * @return int
   */
  protected function deleteEntityRevisions($revision_ids) {

    $deleted_items = 0;
    // Loop trough all entity ids and delete it's applicable keys.
    foreach ($revision_ids as $entity_id => $revisions) {

      foreach (array_keys($revisions) as $revision_id) {
        try {
          $this->entityTypeManager->getStorage('node')
            ->deleteRevision($revision_id);
        }
        catch (\Exception $e) {
          $this->logger->error('Unable to delete revision @rid. Error: @message', [
            '@rid' => $revision_id,
            '@message' => $e->getMessage(),
          ]);
        }

        $deleted_items++;
        if ($deleted_items >= $this->getCronLimit()) {
          return $deleted_items;
        }
      }
    }
    return $deleted_items;
  }

  /**
   * Get all revision ids for the entity type that are not CURRENT revisions.
   *
   * @param string $bundle
   *
   * @return array
   *   Keyed array of entity ids and its revision ids.
   */
  protected function getRevisionIds($bundle, $keep = 0, $changed = '') {
    // Query on the revision table for the entity type.
    $query = $this->database->select('node_field_revision', 'r')->fields('r');

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

    $result = $query->execute();
    $revision_ids = [];
    while ($item = $result->fetchAssoc()) {
      $revision_ids[$item['nid']][$item['vid']] = $item;
    }

    if ($keep > 0) {
      // Slice the revision ids to keep only the number of revisions we want.
      foreach ($revision_ids as &$ids) {
        $ids = array_slice($ids, 0, -$keep, TRUE);
      }
    }

    return array_filter($revision_ids);
  }

}
