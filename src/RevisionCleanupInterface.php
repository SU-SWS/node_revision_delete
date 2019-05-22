<?php

namespace Drupal\node_revision_delete;

/**
 * Interface RevisionCleanupInterface.
 *
 * @package Drupal\node_revision_delete
 */
interface RevisionCleanupInterface {

  const NODE_REVISION_DELETE_AGE = 'age';

  const NODE_REVISION_DELETE_COUNT = 'count';

  /**
   * Delete all revisions for the configured entity types.
   */
  public function deleteRevisions();

}
