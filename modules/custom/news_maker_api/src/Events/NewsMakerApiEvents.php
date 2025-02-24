<?php

namespace Drupal\news_maker_api\Events;

/**
 * Defines events for the NewsMakerApiQueueWorker.
 */
final class NewsMakerApiEvents {

  /**
   * This event allows to subscribe news creation.
   *
   * @var string
   */
  const NEWS_CREATED = 'news_maker_api.news_created';

}
