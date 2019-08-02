<?php

namespace Drupal\Tests\node_revision_delete\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node_revision_delete\RevisionCleanupInterface;

/**
 * Class RevisionCleanupTest
 *
 * @coversDefaultClass \Drupal\node_revision_delete\RevisionCleanup
 * @group node_revision_delete
 */
class RevisionCleanupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'node_revision_delete',
    'user',
    'test_node_revision_delete',
  ];

  /**
   * Node object to test revisions.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Node Revision delete service.
   *
   * @var RevisionCleanupInterface
   */
  protected $revisionCleanup;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig('test_node_revision_delete');

    NodeType::create([
      'type' => 'article',
      'new_revision' => TRUE,
    ])->save();
    $this->installSchema('node', 'node_access');

    $this->node = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
    ]);
    $this->node->setChangedTime(strtotime('-1 year'));
    $this->node->setCreatedTime(strtotime('-1 year'));
    $this->node->save();

    for ($i = 1; $i < 12; $i++) {
      $this->node->set('title', $this->randomString());
      $this->node->setNewRevision();
      $changed_time = strtotime('-' . (12 - $i) . ' months');
      $this->node->setChangedTime($changed_time);
      $this->node->save();
    }
  }

  /**
   *
   */
  public function testCountCleanup() {
    $this->assertEquals(12, $this->getRevisionCount());
    $this->processQueueItems();
    $this->assertEquals(6, $this->getRevisionCount());
    $this->processQueueItems();
    $this->assertEquals(6, $this->getRevisionCount());
  }

  /**
   * Run through cron queue items.
   *
   * @throws \Exception
   */
  protected function processQueueItems() {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = \Drupal::queue('node_revision_delete');
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')
      ->createInstance('node_revision_delete');

    while ($item = $queue->claimItem()) {
      $queue_worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

  /**
   * Get the number of node revisions currently in the database.
   *
   * @return int
   *   Total revisions.
   */
  protected function getRevisionCount() {
    return (int) \Drupal::database()
      ->select('node_revision', 'n')
      ->fields('n')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
