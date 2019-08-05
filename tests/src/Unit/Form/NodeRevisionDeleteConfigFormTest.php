<?php

namespace Drupal\Tests\Kernel\Form;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Queue\QueueFactory;
use Drupal\node_revision_delete\Form\NodeRevisionDeleteConfigForm;
use Drupal\node_revision_delete\RevisionCleanupInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Class NodeRevisionDeleteConfigFormTest.
 *
 * @coversDefaultClass \Drupal\node_revision_delete\Form\NodeRevisionDeleteConfigForm
 * @group node_revision_delete
 */
class NodeRevisionDeleteConfigFormTest extends UnitTestCase {

  /**
   * Form object.
   *
   * @var \Drupal\node_revision_delete\Form\NodeRevisionDeleteConfigForm
   */
  protected $formObject;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $container = new ContainerBuilder();

    $config = [
      'node_revision_delete.settings' => [
        'node_types' => [
          'article' => [
            'method' => RevisionCleanupInterface::NODE_REVISION_DELETE_AGE,
            'keep' => 7,
            'age' => '1 week',
          ],
          'page' => [
            'method' => RevisionCleanupInterface::NODE_REVISION_DELETE_COUNT,
            'keep' => 9,
            'age' => '',
          ],
        ],
      ],
    ];

    $container->set('node_revision_delete', $this->createMock(RevisionCleanupInterface::class));
    $container->set('config.factory', $this->getConfigFactoryStub($config));
    $container->set('entity_type.bundle.info', $this->getBundleInfoStub());
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('queue' , $this->createMock(QueueFactory::class));
    \Drupal::setContainer($container);

    $this->formObject = NodeRevisionDeleteConfigForm::create($container);
  }

  /**
   * Test building the form and its default values are accurate.
   */
  public function testBuildingForm() {
    $this->assertEquals('node_revision_delete_config_form', $this->formObject->getFormId());

    $form = [];
    $form_state = new FormState();
    $form = $this->formObject->buildForm($form, $form_state);

    $this->assertTrue($form['node_types']['article']['enabled']['#default_value']);
    $this->assertEquals(RevisionCleanupInterface::NODE_REVISION_DELETE_AGE, $form['node_types']['article']['method']['#default_value']);
    $this->assertEquals(7, $form['node_types']['article']['keep']['#default_value']);
    $this->assertEquals('1 week', $form['node_types']['article']['age']['#default_value']);

    $this->assertTrue($form['node_types']['page']['enabled']['#default_value']);
    $this->assertEquals(RevisionCleanupInterface::NODE_REVISION_DELETE_COUNT, $form['node_types']['page']['method']['#default_value']);
    $this->assertEquals(9, $form['node_types']['page']['keep']['#default_value']);
    $this->assertEmpty($form['node_types']['page']['age']['#default_value']);

    $this->assertFalse($form['node_types']['blog']['enabled']['#default_value']);
    $this->assertEquals(3, $form['node_types']['blog']['keep']['#default_value']);
  }

  /**
   * Test the age element validation correctly.
   */
  public function testElementValidation() {
    $form = [];
    $form_state = new FormState();
    $form = $this->formObject->buildForm($form, $form_state);

    $element = $form['node_types']['article']['age'];
    $element['#parents'] = ['node_types', 'article', 'age'];

    $this->formObject->validateAgeField($element, $form_state);
    $this->assertFalse($form_state::hasAnyErrors());

    $article_values = [
      'enabled' => TRUE,
      'method' => RevisionCleanupInterface::NODE_REVISION_DELETE_AGE,
      'age' => $this->randomMachineName(),
    ];
    $form_state->setValue(['node_types', 'article'], $article_values);
    $this->formObject->validateAgeField($element, $form_state);
    $this->assertTrue($form_state::hasAnyErrors());
  }

  /**
   * Test the submit form saves.
   */
  public function testSubmitForm() {
    $form = [];
    $form_state = new FormState();

    $submitted_values = [
      'node_types' => [
        'article' => ['enabled' => FALSE],
        'page' => [
          'enabled' => TRUE,
          'method' => RevisionCleanupInterface::NODE_REVISION_DELETE_COUNT,
          'keep' => 6,
        ],
        'blog' => ['enabled' => FALSE],
      ],
    ];
    $form_state->setValues($submitted_values);

    $this->assertNull($this->formObject->submitForm($form, $form_state));

  }

  /**
   * Get the entity bundle info service stub.
   *
   * @return \PHPUnit_Framework_MockObject_MockObject
   */
  protected function getBundleInfoStub() {
    $node_types = [
      'article' => ['label' => 'Article'],
      'page' => ['label' => 'Page'],
      'blog' => ['label' => 'Blog'],
    ];
    $bundle_info = $this->createMock(EntityTypeBundleInfoInterface::class);
    $bundle_info->method('getBundleInfo')->willReturn($node_types);
    return $bundle_info;
  }

}
