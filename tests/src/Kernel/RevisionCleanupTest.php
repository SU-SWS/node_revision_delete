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
  ];

  /**
   * The cron service.
   *
   * @var \Drupal\Core\Cron
   */
  protected $cron;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->cron = \Drupal::service('cron');

    $this->config('node_revision_delete.settings')->set('node_types.article', [
      'method' => 'count',
      'keep' => 5,
      'age' => '',
    ])->save();

    NodeType::create([
      'type' => 'article',
      'new_revision' => TRUE,
    ])->save();
    $this->installSchema('node', 'node_access');

    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
    ]);
    $node->setChangedTime(strtotime('-1 year'));
    $node->setCreatedTime(strtotime('-1 year'));
    $node->save();

    for ($i = 1; $i < 12; $i++) {
      $node->set('title', $this->randomString());
      $node->setNewRevision();
      $changed_time = strtotime('-' . (12 - $i) . ' months');
      $node->setChangedTime($changed_time);
      $node->save();
    }

  }

  /**
   * Test revision delete keeps the configured number of revisions.
   */
  public function testCountCleanup() {
    $this->assertEquals(12, $this->getRevisionCount());
    $this->cron->run();
    $this->assertEquals(6, $this->getRevisionCount());
    $this->cron->run();
    $this->assertEquals(6, $this->getRevisionCount());
  }

  /**
   * Test age cleanup removes old revisions but keeps configured numbers.
   */
  public function testAgeCleanup() {
    $this->config('node_revision_delete.settings')
      ->set('node_types.article.method', 'age')
      ->set('node_types.article.keep', 2)
      ->set('node_types.article.age', '1 month')
      ->save();

    \Drupal::service('node_revision_delete')->queueAllRevisions();

    $this->assertEquals(12, $this->getRevisionCount());
    $this->cron->run();
    $this->assertEquals(2, $this->getRevisionCount());
    $this->cron->run();
    $this->assertEquals(2, $this->getRevisionCount());
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
