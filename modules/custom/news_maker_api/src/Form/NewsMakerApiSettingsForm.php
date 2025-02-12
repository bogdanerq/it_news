<?php

namespace Drupal\news_maker_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configuration form for News Maker API.
 */
class NewsMakerApiSettingsForm extends ConfigFormBase {

  /**
   * A string key editable config name.
   */
  const SETTINGS = 'news_maker_api.settings';

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

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];

    $form['language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
      '#default_value' => $config->get('language'),
      '#description' => $this->t('Comma-separated list of languages (en,uk)'),
    ];

    $form['keywords'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keywords'),
      '#default_value' => $config->get('keywords'),
      '#description' => $this->t('Keywords for news search'),
    ];

    $form['categories'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Categories'),
      '#default_value' => $config->get('categories'),
      '#description' => $this->t('Comma-separated list of categories (general,tech)'),
      '#placeholder' => $this->t('general,tech'),
    ];

    $form['published_before'] = [
      '#type' => 'date',
      '#title' => $this->t('Published Before'),
      '#default_value' => $config->get('published_before'),
    ];

    $form['published_after'] = [
      '#type' => 'date',
      '#title' => $this->t('Published After'),
      '#default_value' => $config->get('published_after'),
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('News Limit'),
      '#default_value' => $config->get('limit') ?: 3,
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
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('language', $form_state->getValue('language'))
      ->set('keywords', $form_state->getValue('keywords'))
      ->set('categories', $form_state->getValue('categories'))
      ->set('published_before', $form_state->getValue('published_before'))
      ->set('published_after', $form_state->getValue('published_after'))
      ->set('limit', $form_state->getValue('limit'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Custom submit handler for the "Fetch News Now" button.
   */
  public function fetchNews(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\news_maker_api\NewsMakerApiFetcher $fetcher */
    $fetcher = \Drupal::service('news_maker_api.fetcher');
    $fetcher->fetchNews();

    $this->messenger()->addMessage($this->t('News items have been enqueued.'));
    // Redirect back to the settings page.
//    $form_state->setRedirectUrl(Url::fromRoute('news_maker_api.settings'));
  }

}
