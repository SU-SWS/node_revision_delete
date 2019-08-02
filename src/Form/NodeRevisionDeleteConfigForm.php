<?php

namespace Drupal\node_revision_delete\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node_revision_delete\RevisionCleanupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodeRevisionDeleteConfigForm.
 */
class NodeRevisionDeleteConfigForm extends ConfigFormBase {

  /**
   * Entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Revision Cleanup Service.
   *
   * @var \Drupal\node_revision_delete\RevisionCleanupInterface
   */
  protected $revisionCleanup;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info'),
      $container->get('node_revision_delete')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeBundleInfoInterface $bundle_info, RevisionCleanupInterface $revision_cleanup) {
    parent::__construct($config_factory);
    $this->bundleInfo = $bundle_info;
    $this->revisionCleanup = $revision_cleanup;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_revision_delete_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['node_revision_delete.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config_settings = $this->config('node_revision_delete.settings');

    $form['node_types'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($this->bundleInfo->getBundleInfo('node') as $bundle => $label) {

      $form['node_types'][$bundle] = [
        '#type' => 'fieldset',
        '#title' => $label['label'],
      ];
      $form['node_types'][$bundle]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Prune Revisions on @label', ['@label' => $label['label']]),
        '#default_value' => !empty($config_settings->get("node_types.$bundle")),
      ];
      $form['node_types'][$bundle]['method'] = [
        '#type' => 'select',
        '#title' => $this->t('Cleanup Method'),
        '#options' => [
          RevisionCleanupInterface::NODE_REVISION_DELETE_COUNT => $this->t('Count'),
          RevisionCleanupInterface::NODE_REVISION_DELETE_AGE => $this->t('Age'),
        ],
        '#default_value' => $config_settings->get("node_types.$bundle.method"),
        '#states' => [
          'visible' => [
            ":input[name=\"node_types[$bundle][enabled]\"]" => ['checked' => TRUE],
          ],
        ],
      ];
      $form['node_types'][$bundle]['keep'] = [
        '#type' => 'number',
        '#title' => $this->t('Revisions to Keep'),
        '#description' => $this->t('Always keep the given number of revisions.'),
        '#default_value' => $config_settings->get("node_types.$bundle.keep") ?: 3,
        '#states' => [
          'visible' => [
            ":input[name=\"node_types[$bundle][enabled]\"]" => ['checked' => TRUE],
          ],
        ],
      ];
      $form['node_types'][$bundle]['age'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Age'),
        '#description' => $this->t('Relative age to delete items. ie "2 weeks" will delete any revision that becomes 2 weeks old'),
        '#size' => 15,
        '#default_value' => $config_settings->get("node_types.$bundle.age"),
        '#element_validate' => [[$this, 'validateAgeField']],
        '#states' => [
          'visible' => [
            ":input[name=\"node_types[$bundle][enabled]\"]" => ['checked' => TRUE],
            ":input[name=\"node_types[$bundle][method]\"]" => ['value' => RevisionCleanupInterface::NODE_REVISION_DELETE_AGE],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * Validate age field for valid format.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state object.
   */
  public function validateAgeField(array $element, FormStateInterface $form_state) {
    $path = array_slice($element['#parents'], 0, 2);
    $settings = $form_state->getValue($path);

    if (!$settings['enabled'] || $settings['method'] != RevisionCleanupInterface::NODE_REVISION_DELETE_AGE) {
      $form_state->setValueForElement($element, '');
      return;
    }

    if (!strtotime($settings['age'])) {
      $form_state->setError($element, $this->t('Invalid age format'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config_settings = $this->config('node_revision_delete.settings');
    $node_types = $form_state->getValue('node_types');

    $node_types = array_filter($node_types, function ($node_settings) {
      return $node_settings['enabled'];
    });

    foreach ($node_types as &$node_settings) {
      unset($node_settings['enabled']);
    }
    $config_settings->set('node_types', $node_types);
    $config_settings->save();
    $this->messenger()->addStatus($this->t('Settings saved successfully.'));

    $this->revisionCleanup->queueAllRevisions();
  }

}
