<?php

namespace Drupal\news_maker_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Drupal\Core\Queue\QueueFactory;

/**
 * Service for fetching news from thenewsapi and enqueuing items.
 */
class NewsMakerApiFetcher {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * Key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new NewsMakerApiFetcher.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory, QueueFactory $queue_factory, KeyRepositoryInterface $key_repository, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->queueFactory = $queue_factory;
    $this->keyRepository = $key_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Fetches news from thenewsapi and enqueues each item.
   */
  public function fetchNews() {
    $config = $this->configFactory->get('news_maker_api.settings');

    if (!$config->get('api_url') && !$config->get('api_key')) {
      $this->loggerFactory->get('news_maker_api')->notice('API key or API url is not configured.');
      return FALSE;
    }
    else {
      $api_url = $config->get('api_url');
      $api_key = $this->keyRepository->getKey($config->get('api_key'));
    }
    $language = $config->get('language');
    $keywords = $config->get('keywords');
    $categories = $config->get('categories');
    $start_date = $config->get('start_date');
    $end_date = $config->get('end_date');
    $limit = $config->get('limit') ?: 20;

    $request_options[RequestOptions::HEADERS]['X-Api-Key'] = $api_key->getKeyValue();

    $query = [];
    if ($language) {
      $query['language'] = $language;
    }
    if ($keywords) {
      $query['q'] = $keywords;
    }
    if ($start_date) {
      $query['start_date'] = $start_date;
    }
    if ($end_date) {
      $query['end_date'] = $end_date;
    }

    // set limit per_page, default 10 (depends on subscription plan)
    $query['per_page'] = $limit;

    // formed api_url
    $api_url .= '?' . http_build_query($query);

    // multiple topics formed like 'topic=technology&topic=economy'
    if ($categories) {
      $categories_array = explode(',', $categories);
      $api_url .= '&' . implode('&', array_map(fn($topic) => "topic=" . $topic, $categories_array));
    }

    $news_count = 0;
    $next_cursor = '';
    try {
      // do-while cycle to process the next page request
      do {
        // cursor parameter using for get next page
        if (!empty($next_cursor)) {
          $request_url = $api_url . '&' . 'cursor=' . $next_cursor;
        }
        else {
          $request_url = $api_url;
        }
        $response = $this->httpClient->request('GET', $request_url, $request_options);
        if ($response->getStatusCode() == 200) {
          $data = json_decode($response->getBody(), TRUE);
          if (!empty($data['data']) && is_array($data['data'])) {
            $queue = $this->queueFactory->get('news_maker_api_queue_worker');
            $queue->createQueue();

            // check and ignore existing news
            $uuids = array_column($data['data'], 'id');
            $existing_nodes = $this->entityTypeManager->getStorage('node')
              ->getQuery()
              ->accessCheck()
              ->condition('field_uuid', $uuids, 'IN')
              ->execute();

            // get existing news id
            $existing_uuids = [];
            if (!empty($existing_nodes)) {
              $nodes = $this->entityTypeManager->getStorage('node')
                ->loadMultiple($existing_nodes);
              foreach ($nodes as $node) {
                $existing_uuids[] = $node->get('field_uuid')->value;
              }
            }

            // Create only unique news
            foreach ($data['data'] as $news_item) {
              if (!in_array($news_item['id'], $existing_uuids, true)) {
                $queue->createItem($news_item);
              }
            }
            $news_count = $queue->numberOfItems();
          }
          $next_cursor = $data['next_cursor'] ?? '';
        }
      } while (!empty($data['data']) && !empty($next_cursor) && $news_count < $limit);
      $success = TRUE;
    }
    catch (\Exception $e) {
      $success = FALSE;
      $this->loggerFactory->get('news_maker_api')->error($e->getMessage());
    }

    return $success;
  }

}
