# Drupal 10 Project on Docker Platform

This project is a Drupal 10 website built for a test site called **IT News**. It leverages Docker for containerized deployment and includes custom modules, themes, and several contributed modules to enhance functionality.

## Table of Contents

- [Docker Setup](#docker-setup)
- [Git and Deployment Configuration](#git-and-deployment-configuration)
- [Site Features](#site-features)
  - [Homepage](#homepage)
  - [Content Pages](#content-pages)
- [Styling and Theming](#styling-and-theming)
- [Customization and Custom Modules](#customization-and-custom-modules)
- [Module References and Links](#module-references-and-links)

## Docker Setup

- **Docker Compose:** The project uses a `docker-compose.yml` file that sets up the Docker environment. In this file, the `build: .` directive ensures that the Dockerfile in the project root is used.
- **Xdebug Configuration:** The Dockerfile is configured to install and enable Xdebug with its configuration file `xdebug.ini`. Note that the Xdebug port is set to **9005** (instead of the default 9003) to avoid conflicts.
- **Deployment Script:** An additional deployment script (`deploy-drupal.sh`) is provided for automatic deployment. This script can be modified as needed.
- **Directory with files:** https://drive.google.com/drive/folders/1kTyjDdnx6H3wMizF2rGgzrLp42LfQ6XT?usp=drive_link

For more details on Docker and Docker Compose, see the [Docker documentation](https://www.docker.com) and the [Compose documentation](https://docs.docker.com/compose/).

## Git and Deployment Configuration

- **Configuration Files:** The files `services.yml` and `settings.php` are intentionally included in the repository (i.e. not ignored). While this is not recommended for production or for a site on sale (since they may contain sensitive information such as database credentials), their inclusion simplifies deployment in this context.

## Site Features

### Homepage

- **News Slider:** The main page features a slider (powered by the [Views Slideshow](https://www.drupal.org/project/views_slideshow) module) that displays news items. To include a news item in the slider, mark it with the **Promoted to front page** flag.
- **Category View:** There is a view that displays a taxonomy term (the news category) along with two news items associated with that term. This is implemented via a view field (`views_field_view`).
- **Page Layout:** The homepage layout is constructed using Paragraphs for flexible content management.
- **Language Switching:** A block provided by the [Advanced Language Selector](https://www.drupal.org/project/advanced_language_selector) module allows users to switch between two configured languages.

### Content Pages

- **All News View:** The main content page, titled **All News**, is implemented as a view. It includes filters for categories, news period, and keywords (serving as a search feature), and sorts news by creation date (from oldest to newest).
- **Contact Us Form:** The **Contact Us** page features a two-step webform:
  - **Step 1:** Users select a reason for contact.
  - **Step 2:** The form presents detailed fields that vary based on the selection made in the first step.

## Styling and Theming

- **Dark/Light Mode:** A module named **dark_mode_toggle** enables users to switch between light and dark modes. This module works by simply adding a `dark` class to the `<html>` element.
- **Subtheme:** The custom subtheme **subtheme_bootstrap** is based on the [Bootstrap](https://www.drupal.org/project/bootstrap) theme. It includes adjustable settings via the **Bootstrap Color Scheme Setting**, allowing you to tweak the custom styles and color schemes.

## Customization and Custom Modules

- **News Maker API Module:** The core custom functionality is built into the `news_maker_api` module. It integrates with the [NewsDataHub API](https://newsdatahub.com) to fetch news:
  - **Service:** `NewsMakerApiFetcher` retrieves data from the API and queues items for processing.
  - **Settings Form:** Accessible at [News Maker API Settings](http://localhost:8080/admin/config/news-maker-api/settings), this form lets you configure API parameters and provides two manual action buttons:
    - **Fetch News Now:** Manually trigger a news fetch.
    - **Run Queue Process:** Process news items already in the queue.
  - **Queue Worker:** `NewsMakerApiQueueWorker` processes queued items and creates news content.
  - **Events:** Upon creating news, the module triggers an event (`NewsMakerApiEvents`) used by other modules.

- **News Auto Translate Submodule:** The `news_auto_translate` submodule automatically translates news items when they are created:
  - **Settings:** Enable or disable automatic translation at [News Auto Translate Settings](http://localhost:8080/admin/config/news-maker-api/news-auto-translate).
  - **Event Listener:** The `NodeTranslationSubscriber` listens for events triggered by `news_maker_api` and uses the [Auto Translation](https://www.drupal.org/project/auto_translation) module to translate available fields.

## Module References and Links

- **Drupal Core:** [Drupal 10](https://www.drupal.org/project/drupal)
- **Views Slideshow:** [Views Slideshow Module](https://www.drupal.org/project/views_slideshow)
- **Advanced Language Selector:** [Advanced Language Selector Module](https://www.drupal.org/project/advanced_language_selector)
- **Bootstrap Theme:** [Bootstrap Theme](https://www.drupal.org/project/bootstrap)
- **Auto Translation:** [Auto Translation Module](https://www.drupal.org/project/auto_translation)
- **NewsDataHub API:** [NewsDataHub Website](https://newsdatahub.com)
- **Docker:** [Docker Official Site](https://www.docker.com)
- **Docker Compose:** [Docker Compose Documentation](https://docs.docker.com/compose/)

---

This README provides an overview of the project setup, features, and customizations. Modify the deployment script (`deploy-drupal.sh`) and other configurations as needed for your development or production environment.
