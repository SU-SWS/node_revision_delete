<?php

namespace Drupal\node_revision_delete;

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
   * Delete all revisions for the configured entity types.
   */
  public function deleteRevisions();

}
