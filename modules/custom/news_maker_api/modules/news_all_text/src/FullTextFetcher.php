<?php

namespace Drupal\news_all_text;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for receiving the full text of a news article.
 */
class FullTextFetcher {

  /**
   * The HTTP client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs a new FullTextFetcher.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel.
   */
  public function __construct(ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('news_all_text');
  }

  /**
   * Gets the full text of the article at the given URL.
   *
   * @param string $url
   *   URL article.
   *
   * @return string
   *   Full text of the article or an empty line on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFullText($url) {
    try {
      $response = $this->httpClient->request('GET', $url);
      if ($response->getStatusCode() == 200) {
        $html = (string) $response->getBody();
        // Simplified: remove HTML tags.
        return strip_tags($html);
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
    return '';
  }
}
