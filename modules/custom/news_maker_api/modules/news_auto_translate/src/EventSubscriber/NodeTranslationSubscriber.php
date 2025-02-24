<?php

namespace Drupal\news_auto_translate\EventSubscriber;

use Drupal\auto_translation\Utility;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
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
   * Constructs a NodeTranslationSubscriber object.
   *
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $field_manager, Utility $auto_translation_utility) {
    $this->configFactory = $config_factory;
    $this->fieldManager = $field_manager;
    $this->autoTranslationUtility = $auto_translation_utility;
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
    $t_lang = $config->get('translation_language');

    if ($entity instanceof Node && $config->get('enable_translation') && !$entity->hasTranslation($t_lang)) {

      $translated_fields = $entity->toArray();
      $enabledContentTypes = $this->autoTranslationUtility->getEnabledContentTypes();
      $entity_type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
      $arrCheck = $this->autoTranslationUtility->getExcludedFields();
      if ($enabledContentTypes && in_array($bundle, $enabledContentTypes)) {
        $fields = $this->fieldManager->getFieldDefinitions($entity_type, $bundle);
        $d_lang = $entity->language()->getId();

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
