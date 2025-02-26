<?php

namespace Drupal\news_auto_translate\EventSubscriber;

use Drupal\auto_translation\Utility;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\news_maker_api\Events\NewsMakerApiEvents;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Listens for the event when a news node is created.
 */
class NodeTranslationSubscriber implements EventSubscriberInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\auto_translation\Utility
   */
  protected Utility $autoTranslationUtility;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a NodeTranslationSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A configuration factory instance.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\auto_translation\Utility $auto_translation_utility
   *   The Utility class for auto_translation module functions.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $field_manager, Utility $auto_translation_utility, LanguageManagerInterface $language_manager) {
    $this->configFactory = $config_factory;
    $this->fieldManager = $field_manager;
    $this->autoTranslationUtility = $auto_translation_utility;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[NewsMakerApiEvents::NEWS_CREATED][] = 'onNewsCreated';
    return $events;
  }

  /**
   * Handles the news node creation event.
   *
   * @param \Symfony\Component\EventDispatcher\GenericEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onNewsCreated(GenericEvent $event) {
    /** @var \Drupal\node\Entity\Node $entity */
    $entity = $event->getSubject();
    $config = $this->configFactory->get('news_auto_translate.settings');

    if ($entity instanceof Node && $config->get('enable_translation')) {

      $translated_fields = $entity->toArray();
      $enabledContentTypes = $this->autoTranslationUtility->getEnabledContentTypes();
      $entity_type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
      $arrCheck = $this->autoTranslationUtility->getExcludedFields();
      if ($enabledContentTypes && in_array($bundle, $enabledContentTypes)) {
        $fields = $this->fieldManager->getFieldDefinitions($entity_type, $bundle);
        $d_lang = $entity->language()->getId();

        // Get the available translation language (only for a bilingual site)
        $languages = $this->languageManager->getLanguages();
        $other_language = current(array_filter($languages, fn($lang) => $lang->getId() !== $d_lang)) ?? NULL;
        $t_lang = $other_language ? $other_language->getId() : NULL;
        if (!isset($t_lang) || $entity->hasTranslation($t_lang)) {
          return;
        }

        foreach ($fields as $field) {
          $field_name = $field->getName();
          $field_type = $field->getType();

          // Translatable field support
          if ($field->isTranslatable()) {
            // Translate field
            if (
              is_string($entity->get($field_name)->value)
              && !in_array(strtolower($entity->get($field_name)->value), $arrCheck)
              && $field_name != "langcode"
              && !is_numeric($entity->get($field_name)->value)
              && !in_array($field_type, $arrCheck)
            ) {
              $string = $entity->get($field_name)->value ? (string) $entity->get($field_name)->value : '';
              // Translate field with summary
              if ($field_type == 'text_with_summary') {
                $summary = $entity->get($field_name)->summary ? (string) $entity->get($field_name)->summary : '';
                if (!empty($summary) && $summary !== '') {
                  $translationResponse = $this->autoTranslationUtility->translate($summary, $d_lang, $t_lang);
                  if ($translationResponse) {
                    $translated_fields[$field_name][0]['summary'] = $translationResponse;
                  }
                }
              }
              if (!empty($string) && $string !== '' && strip_tags($string) !== '' && !empty(strip_tags($string))) {
                $translationResponse = $this->autoTranslationUtility->translate($string, $d_lang, $t_lang);
                if ($translationResponse) {
                  $translated_fields[$field_name][0]['value'] = $translationResponse;
                }
              }
            }
          }
        }

        // Create node translation
        $translated = $entity->addTranslation($t_lang, $translated_fields);
        $translated->save();
      }
    }
  }

}
