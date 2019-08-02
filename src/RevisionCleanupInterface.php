<?php

namespace Drupal\node_revision_delete;

use Drupal\node\NodeInterface;

/**
 * Interface RevisionCleanupInterface.
 *
 * @package Drupal\node_revision_delete
 */
interface RevisionCleanupInterface {

  /**
   * Delete revisions based on their last changed date.
   */
  const NODE_REVISION_DELETE_AGE = 'age';

  /**
   * Delete revisions based on the number of revisions.
   */
  const NODE_REVISION_DELETE_COUNT = 'count';

  /**
   * Delete a specific revision
   *
   * @param int $nid
   *   Node ID.
   * @param int $vid
   *   Revision ID.
   */
  public function deleteRevision($nid, $vid);

  /**
   * Queue any revisions for the provided node to be deleted.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity object.
   */
  public function queueRevisionsForNode(NodeInterface $node);

  /**
   * Query the database and queue up every revision for possible deletion.
   */
  public function queueAllRevisions();

}
