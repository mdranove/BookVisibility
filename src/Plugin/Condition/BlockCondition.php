<?php

namespace Drupal\book\Plugin\Condition;

use Drupal\book\BookManagerInterface;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block visibility condition for books.
 *
 * This condition evaluates to TRUE when the current node belongs to the
 * specified book.
 *
 * @Condition(
 *   id = "book",
 *   label = @Translation("Book"),
 * )
 */
class BookCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructs a new BookOutline.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected $bookManager;


  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManagerProperty;

  public function __construct(BookManagerInterface $bookManager,
  ResettableStackedRouteMatchInterface $currentRouteMatch,
  EntityTypeManagerInterface $entityTypeManagerInjected,
  array $configuration,
  $plugin_id,
  $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->bookManager = $bookManager;
    $this->currentRouteMatch = $currentRouteMatch;
    $this->entityTypeManagerProperty = $entityTypeManagerInjected;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('book.manager'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // The default configuration will be have the block hidden (0).
    return ['show' => 0] + parent::defaultConfiguration();
  }

  /**
   * Get a list of all books for the configuration form.
   */
  public function getBookOptions() {
    $book_list = [];

    foreach (array_keys($this->bookManager->getAllBooks()) as $book) {
      $book = $this->entityTypeManagerProperty->getStorage('node')->load($book);
      $book = ucfirst($book->label());
      $book_list[$book] = $book;
    }
    return $book_list;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Define the checkbox to enable the condition.
    if (isset($this->configuration['book_visibility'])) {
      $book_visiblity = $this->configuration['book_visibility'];
    }
    else {
      $book_visiblity = [];
    }

    $form['book_visibility'] = [
      '#title' => $this->t('Available books'),
      '#type' => 'checkboxes',
      '#options' => $this->getBookOptions(),
      // Use whatever value is stored in cofinguration as the default.
      '#default_value' => $book_visiblity ? $book_visiblity : [],
      '#description' => $this->t('Check the boxes to restrict visibility to the specified books.'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Save the submitted value to configuration.
    $this->configuration['book_visibility'] = $form_state->getValue('book_visibility');

    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if ($this->configuration['book_visibility']) {

      return $this->t('Restricted to specified books');
    }
    else {
      return $this->t('Not restricted');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Determine if the current route matches the specified book.
    if ($this->currentRouteMatch->getParameter('node')->book) {
      $current_book_id = $this->currentRouteMatch->getParameter('node')->book['bid'];
      $book = $this->entityTypeManagerProperty->getStorage('node')->load($current_book_id);
      $book = ucfirst($book->label());

      foreach (array_values($this->configuration['book_visibility']) as $config) {
        if (is_string($config)) {
          if ($config === $book) {
            return TRUE;
          }
        }
      }
    }
  }

}
