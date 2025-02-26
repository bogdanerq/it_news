<?php

namespace Drupal\news_auto_translate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * News Auto Translate settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * A string key editable config name.
   */
  const SETTINGS = 'news_auto_translate.settings';

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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config(static::SETTINGS)
      ->set('enable_translation', $form_state->getValue('enable_translation'))
      ->save();
  }
}
