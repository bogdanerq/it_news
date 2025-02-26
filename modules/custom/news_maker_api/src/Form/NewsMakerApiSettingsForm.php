<?php

namespace Drupal\news_maker_api\Form;

use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\news_maker_api\NewsMakerApiFetcher;

/**
 * Configuration form for News Maker API.
 */
class NewsMakerApiSettingsForm extends ConfigFormBase {

  /**
   * A string key editable config name.
   */
  const SETTINGS = 'news_maker_api.settings';

  /**
   * The News Maker Api fetcher.
   *
   * @var \Drupal\news_maker_api\NewsMakerApiFetcher
   */
  protected NewsMakerApiFetcher $apiFetcher;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a new NegotiationUrlForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\news_maker_api\NewsMakerApiFetcher $fetcher
   *   The News Maker Api fetcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, NewsMakerApiFetcher $fetcher, LanguageManagerInterface $language_manager) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->apiFetcher = $fetcher;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('news_maker_api.fetcher'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'news_maker_api_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API url'),
      '#default_value' => $config->get('api_url'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];

    $languages = $this->languageManager->getLanguages();
    $language_options = [];
    foreach ($languages as $language) {
      $language_options[$language->getId()] = $language->getName();
    }

    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $language_options,
      '#default_value' => $config->get('language'),
    ];

    $form['keywords'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keywords'),
      '#default_value' => $config->get('keywords'),
      '#placeholder' => $this->t('apple banana or "apple pie"'),
      '#description' => $this->t('Keywords for news search, separated by a space or for exact phrases "apple pie"'),
    ];

    $form['categories'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Categories'),
      '#default_value' => $config->get('categories'),
      '#description' => $this->t('Comma-separated list of categories (general, tech)'),
      '#placeholder' => $this->t('general, tech'),
    ];

    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('From Date'),
      '#default_value' => $config->get('start_date'),
    ];

    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Until Date'),
      '#default_value' => $config->get('end_date'),
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('News Limit'),
      '#default_value' => $config->get('limit') ?: 20,
      '#min' => 1,
      '#description' => $this->t('Number of news items to receive'),
    ];

    // Add the default Save configuration button.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    // Add an extra button to manually fetch news.
    $form['actions']['fetch_news'] = [
      '#type' => 'submit',
      '#value' => $this->t('Fetch News Now'),
      // Specify a separate submit handler.
      '#submit' => ['::fetchNews'],
      // Skip validation to allow manual triggering.
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('language', $form_state->getValue('language'))
      ->set('keywords', $form_state->getValue('keywords'))
      ->set('categories', $form_state->getValue('categories'))
      ->set('start_date', $form_state->getValue('start_date'))
      ->set('end_date', $form_state->getValue('end_date'))
      ->set('limit', $form_state->getValue('limit'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Custom submit handler for the "Fetch News Now" button.
   */
  public function fetchNews(array &$form, FormStateInterface $form_state) {
    if ($this->apiFetcher->fetchNews()) {
      $this->messenger()->addMessage($this->t('News items have been enqueued.'));
    }
    else {
      $this->messenger()->addError($this->t('Some shit happened, check Recent log messages.'));
    }
  }

}
