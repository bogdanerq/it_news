<?php

namespace Drupal\news_maker_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
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
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

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
   * Constructs a new NewsMakerApiFetcher.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerInterface $logger, QueueFactory $queue_factory, KeyRepositoryInterface $key_repository) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $logger;
    $this->queueFactory = $queue_factory;
    $this->keyRepository = $key_repository;
  }

  /**
   * Fetches news from thenewsapi and enqueues each item.
   */
  public function fetchNews() {
    $config = $this->configFactory->get('news_maker_api.settings');

    if (!$config->get('api_key')) {
      $this->logger->notice('API key is not selected.');
      return;
    }
    else {
      $api_key = $this->keyRepository->getKey($config->get('api_key'));
    }
    $language = $config->get('language');
    $keywords = $config->get('keywords');
    $categories = $config->get('categories');
    $published_before = $config->get('published_before');
    $published_after = $config->get('published_after');
    $limit = $config->get('limit') ?: 3;

    $query = [
      'api_token' => $api_key->getKeyValue(),
      'limit' => $limit,
    ];
    if ($language) {
      $query['language'] = $language;
    }
    if ($keywords) {
      $query['search'] = $keywords;
    }
    if ($categories) {
      $query['categories'] = $categories;
    }
    if ($published_before) {
      $query['published_before'] = $published_before;
    }
    if ($published_after) {
      $query['published_after'] = $published_after;
    }

    $api_url = 'https://api.thenewsapi.com/v1/news/all?' . http_build_query($query);

    // @todo delete and uncomment response after tests
    $data = (array) json_decode('{"meta":{"found":15341313,"returned":1,"limit":1,"page":1},"data":[{"uuid":"7ada4fb7-34e3-4262-85b8-f7dd19e913f4","title":"Footwear Finds: Loewe Ballet Runner 2.0","description":"Picture the perfect fusion of a 1970s running shoe and a ballet flat. What a covetable kick. And yet, it`s not imaginary, nor is it fantasy. It`s Loewe`s latest...","keywords":"","snippet":"Picture the perfect fusion of a 1970s running shoe and a ballet flat. What a covetable kick. And yet, it’s not imaginary, nor is it fantasy. It’s Loewe‘s ...","url":"https:\/\/10magazine.com\/footwear-finds-loewe-ballet-runner-2-0\/","image_url":"https:\/\/10magazine.com\/wp-content\/uploads\/2025\/02\/Loewe-FT.jpg","language":"en","published_at":"2025-02-12T12:38:36.000000Z","source":"10magazine.com","categories":["tech"],"relevance_score":null}]}', TRUE);

    try {
//      $response = $this->httpClient->request('GET', $api_url);
//      if ($response->getStatusCode() == 200) {
//        $data = json_decode($response->getBody(), TRUE);
        if (!empty($data['data']) && is_array($data['data'])) {
          $queue = $this->queueFactory->get('news_maker_api_queue_worker');
          $queue->createQueue();
          foreach ($data['data'] as $news_item) {
            $queue->createItem($news_item);
          }
        }
      //      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }
}
