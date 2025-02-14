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
   * Gets the full text of the news article for a given URL.
   *
   * This method fetches the HTML content of the article, then constructs a prompt
   * instructing the AI to extract the main article text (removing extraneous content).
   *
   * @param string $url
   *   The URL of the news article.
   *
   * @return string
   *   The extracted full text of the news article, or an empty string on failure.
   */
  public function getFullText(string $url): string {
    // Fetch the HTML content.
    try {
      // For simplicity, we use file_get_contents here. In production, use a proper HTTP client.
      $html = file_get_contents($url);
    }
    catch (\Exception $e) {
      $this->logger->error('Error fetching URL @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
      return '';
    }

    // Build the prompt for the AI.
    $prompt = "Extract the full text of the news article from the following HTML content. Remove all extraneous elements (navigation, ads, styles) and return only the main article text:\n\n" . $html;

    // Get the default AI engine.
    try {
      /** @var  \Drupal\ai\AiProviderPluginManager $service */
      $service = \Drupal::service('ai.provider');

      if (!$service->hasProvidersForOperationType('chat', TRUE)) {
        $this->logger->error('Sorry, no provider exists for Chat, install one first');
        return '';
      }

//      @todo
//        $engine = $service->;
//      if (!$engine) {
//        $this->logger->error('No default AI engine configured.');
//        return '';
//      }

    }
    catch (\Exception $e) {
      $this->logger->error('Error calling AI engine: @message', ['@message' => $e->getMessage()]);
    }

    return '';
  }

}
