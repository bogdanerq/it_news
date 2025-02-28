FROM drupal:10

RUN pecl install xdebug
RUN apt-get update && apt-get install -y git
