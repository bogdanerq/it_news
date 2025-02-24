<?php

namespace Drupal\news_auto_translate\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * News Auto Translate settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * A string key editable config name.
   */
  const SETTINGS = 'news_auto_translate.settings';

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
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, LanguageManagerInterface $language_manager) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('language_manager')
    );
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
  public function getFormId() {
    return 'news_auto_translate_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['enable_translation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable auto translation news'),
      '#default_value' => $config->get('enable_translation'),
    ];

    $languages = $this->languageManager->getLanguages();
    $language_options = [];
    foreach ($languages as $language) {
      $language_options[$language->getId()] = $language->getName();
    }

    $form['translation_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Translation language'),
      '#options' => $language_options,
      '#default_value' => $config->get('translation_language'),
      '#states' => [
        'visible' => [
          ':input[name="enable_translation"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config(static::SETTINGS)
      ->set('enable_translation', $form_state->getValue('enable_translation'))
      ->set('translation_language', $form_state->getValue('translation_language'))
      ->save();
  }
}
