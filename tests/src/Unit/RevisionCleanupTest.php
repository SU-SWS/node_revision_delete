<?php

namespace Drupal\Tests\node_revision_delete\Unit;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node_revision_delete\RevisionCleanup;
use Drupal\node_revision_delete\RevisionCleanupInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Class RevisionCleanupTest.
 *
 * @coversDefaultClass \Drupal\node_revision_delete\RevisionCleanup
 * @group node_revision_delete
 */
class RevisionCleanupTest extends UnitTestCase {

  /**
   * Test if a single entity fails to load error.
   */
  public function testIndividualError() {
    $query = $this->createMock(StatementInterface::class);
    $query->method('fetchAssoc')->willReturnCallback([
      $this,
      'fetchAssocCallback',
    ]);

    $select = $this->createMock(Select::class);
    $select->method('fields')->will($this->returnValue($select));
    $select->method('execute')->willReturn($query);

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($select);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $entity_type_manager->method('getStorage')
      ->willThrowException(new \Exception('Failed'));

    $logger = $this->createMock(LoggerChannelInterface::class);

    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($logger);

    $config = $this->createMock(Config::class);
    $config->method('get')->willReturnCallback([$this, 'configGetCallback']);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);

    $cleanup = new RevisionCleanup($database, $entity_type_manager, $logger_factory, $config_factory);
    $this->assertNull($cleanup->deleteRevisions());
  }


  /**
   * Callback method when a config object is called to get its settings.
   *
   * @param string $arg
   *   Config setting key.
   *
   * @return mixed
   *   Expected settings.
   */
  public function configGetCallback($arg) {
    switch ($arg) {
      case 'node_types':
        return [
          'page' => [
            'method' => RevisionCleanupInterface::NODE_REVISION_DELETE_COUNT,
            'keep' => 5,
          ],
        ];
        break;
    }
  }

  /**
   * Db fetch callback.
   *
   * @return array
   *   Array of data.
   */
  public function fetchAssocCallback() {
    static $count = 1;
    $count++;

    if ($count < 10) {
      return ['nid' => 1, 'vid' => $count];
    }
  }

}
