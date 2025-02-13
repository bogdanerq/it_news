<?php

namespace Drupal\news_maker_api\Plugin\QueueWorker;

use Drupal\Core\Annotation\QueueWorker;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\File\FileRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  protected $entityTypeManager;

  /**
   * The file repository service.
   *
   * @var \Drupal\File\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, FileRepositoryInterface $file_repository, ClientInterface $http_client, LoggerInterface $logger, FileSystemInterface $file_system) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->fileRepository = $file_repository;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->fileSystem = $file_system;
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
      $container->get('file_system')
    );
  }

  /**
   * Processes one news item from the queue.
   *
   * @param array $data
   *   An associative array containing the news data.
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
    if (\Drupal::moduleHandler()->moduleExists('news_all_text')) {
      // If the 'News all text' submodule is enabled, attempt to fetch the full text.
      $full_text = \Drupal::service('news_all_text.full_text')->getFullText($data['url']);
      $body_content = $full_text ?: $data['snippet'];
    }
    else {
      $body_content = $data['snippet'];
    }

    // Create an article node with the mapped fields.
    $node = Node::create([
      'type' => 'article',
      'title' => $data['title'],
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
  }

  /**
   * Retrieves an existing or creates a new taxonomy term.
   *
   * @param string $name
   *   The term name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The taxonomy term entity or NULL on failure.
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
  protected function downloadImage($url) {
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
