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
   * Node object to test revisions.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');

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

    $this->installConfig('node_revision_delete');
    $config_settings = [
      'article' => [
        'method' => RevisionCleanupInterface::NODE_REVISION_DELETE_COUNT,
        'keep' => 5,
      ],
    ];
    $this->config('node_revision_delete.settings')
      ->set('node_types', $config_settings)
      ->save();
  }

  /**
   *
   */
  public function testCountCleanup() {
    $this->assertEquals(12, $this->getRevisionCount());
    \Drupal::service('node_revision_delete')->deleteRevisions();
    $this->assertEquals(5, $this->getRevisionCount());
    \Drupal::service('node_revision_delete')->deleteRevisions();
    $this->assertEquals(5, $this->getRevisionCount());
  }

  /**
   */
  public function testCronLimit() {
    $this->config('node_revision_delete.settings')
      ->set('cron_limit', 2)
      ->save();

    $this->assertEquals(12, $this->getRevisionCount());
    \Drupal::service('node_revision_delete')->deleteRevisions();
    $this->assertEquals(10, $this->getRevisionCount());
    \Drupal::service('node_revision_delete')->deleteRevisions();
    $this->assertEquals(8, $this->getRevisionCount());
  }

  public function testAgeCleanup() {
    $config_settings = [
      'article' => [
        'method' => RevisionCleanupInterface::NODE_REVISION_DELETE_AGE,
        'age' => '1 month',
      ],
    ];
    $this->config('node_revision_delete.settings')
      ->set('node_types', $config_settings)
      ->save();

    $this->assertEquals(12, $this->getRevisionCount());
    \Drupal::service('node_revision_delete')->deleteRevisions();
    $this->assertEquals(1, $this->getRevisionCount());
    \Drupal::service('node_revision_delete')->deleteRevisions();
    $this->assertEquals(1, $this->getRevisionCount());
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
