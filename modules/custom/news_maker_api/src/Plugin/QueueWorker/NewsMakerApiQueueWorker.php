<?php

namespace Drupal\news_maker_api\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\news_maker_api\Events\NewsMakerApiEvents;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\File\FileRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Processes news items retrieved from the News Maker API.
 *
 * @QueueWorker(
 *   id = "news_maker_api_queue_worker",
 *   title = @Translation("News Maker API Queue Worker"),
 *   cron = {"time" = 60}
 * )
 */
class NewsMakerApiQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file repository service.
   *
   * @var \Drupal\File\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a new NewsMakerApiQueueWorker.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\File\FileRepositoryInterface $file_repository
   *   The file repository service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, FileRepositoryInterface $file_repository, ClientInterface $http_client, LoggerInterface $logger, FileSystemInterface $file_system, ModuleHandlerInterface $module_handler, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->fileRepository = $file_repository;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file.repository'),
      $container->get('http_client'),
      $container->get('logger.factory')->get('news_maker_api'),
      $container->get('file_system'),
      $container->get('module_handler'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Processes one news item from the queue.
   *
   * @param array $data
   *   An associative array containing the news data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function processItem($data) {
    // Check if a node with the same UUID already exists.
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'article',
      'field_uuid' => $data['uuid'],
    ]);
    if (!empty($nodes)) {
      return;
    }

    // Process categories: create or retrieve taxonomy terms for the 'tags' vocabulary.
    $term_ids = [];
    if (!empty($data['categories']) && is_array($data['categories'])) {
      foreach ($data['categories'] as $category) {
        $term = $this->getOrCreateTerm(trim($category));
        if ($term) {
          $term_ids[] = $term->id();
        }
      }
    }

    // Download the image file if provided.
    $file_id = NULL;
    if (!empty($data['image_url'])) {
      $file_id = $this->downloadImage($data['image_url']);
    }

    // Determine body content.
    if ($this->moduleHandler->moduleExists('news_all_text')) {
      // If the 'News all text' submodule is enabled, attempt to fetch the full text.
      /** @var \Drupal\news_all_text\FullTextFetcher $sub_service */
      $sub_service = \Drupal::service('news_all_text.full_text');
      $full_text = $sub_service->getFullText($data['url']);
      $body_content = $full_text ?: $data['snippet'];
    }
    else {
      $body_content = $data['snippet'];
    }

    // Create an article node with the mapped fields.
    $node = Node::create([
      'type' => 'article',
      'title' => $data['title'],
      'created' => strtotime($data['published_at']),
      'field_uuid' => $data['uuid'],
      'field_link' => ['uri' => $data['url']],
      'field_source' => $data['source'],
      'field_tags' => $term_ids,
      'body' => [
        'value' => $body_content,
        'summary' => $data['description'],
        'format' => 'basic_html',
      ],
    ]);
    if ($file_id) {
      $node->set('field_image', ['target_id' => $file_id]);
    }
    $node->save();

    // Event for translate by news_auto_translate
    $event = new GenericEvent($node);
    $this->eventDispatcher->dispatch($event, NewsMakerApiEvents::NEWS_CREATED);
  }

  /**
   * Retrieves an existing or creates a new taxonomy term.
   *
   * @param string $name
   *   The term name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The taxonomy term entity or NULL on failure.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getOrCreateTerm($name) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'name' => $name,
      'vid' => 'tags',
    ]);
    if ($term = reset($terms)) {
      return $term;
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
      'vid' => 'tags',
      'name' => $name,
    ]);
    $term->save();
    return $term;
  }

  /**
   * Downloads an image from a URL and saves it as a file entity.
   *
   * This function uses the injected file repository service and file system
   * service to write the image data to the public file system.
   *
   * @param string $url
   *   The image URL.
   *
   * @return int|null
   *   The file entity ID or NULL on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function downloadImage(string $url) {
    try {
      $response = $this->httpClient->request('GET', $url, ['stream' => TRUE]);
      if ($response->getStatusCode() == 200) {
        $data = $response->getBody()->getContents();
        $file_name = basename(parse_url($url, PHP_URL_PATH));
        $file_directory = 'public://news_images';
        $destination = $file_directory . "/{$file_name}";
        // Ensure the destination directory exists.
        $this->fileSystem->prepareDirectory($file_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        // Save the file using the file repository service.
        $file = $this->fileRepository->writeData($data, $destination);
        if ($file) {
          $file->setPermanent();
          $file->save();
          return $file->id();
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
    return NULL;
  }

}
