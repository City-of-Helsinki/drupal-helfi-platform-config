<?php

declare(strict_types=1);

namespace Drupal\helfi_node_survey\Entity;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\helfi_platform_config\Entity\PublishExternallyInterface;
use Drupal\helfi_platform_config\Entity\PublishExternallyTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Bundle class for Survey node.
 */
class Survey extends Node implements NodeInterface, PublishExternallyInterface {

  use PublishExternallyTrait;

  /**
   * The cache tag of remote surveys on other instances.
   */
  public const EXTERNAL_CACHE_TAG = 'helfi_external_entity_survey';

  /**
   * {@inheritdoc}
   */
  public function getExternalCacheTags(): array {
    return [self::EXTERNAL_CACHE_TAG];
  }

  /**
   * Populates the survey from a remote survey.
   *
   * Remote surveys are rendered as unsaved survey nodes.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $remote
   *   The remote survey.
   *
   * @return $this
   *   The survey.
   */
  public function populateFromRemoteEntity(FieldableEntityInterface $remote): static {
    $linkUrl = NULL;
    $linkText = NULL;
    if ($remote->hasField('survey_link_text')) {
      $linkText = $remote->get('survey_link_text')->value;
      $linkUrl = $remote->get('survey_link_url')->value;
    }

    $this->set('uuid', $remote->get('uuid')->value);
    $this->set('langcode', $remote->get('langcode')->value);
    $this->set('title', Xss::filter($remote->get('title')->value));
    $this->set('body', Xss::filter($remote->get('body')->value));
    $this->set('status', $remote->get('status')->value);
    $this->set('field_survey_link', ['uri' => $linkUrl, 'title' => $linkText]);
    $this->set('published_at', $this->toTimestamp($remote->get('published_at')->value));

    return $this;
  }

  /**
   * Converts a remote date to a timestamp.
   *
   * @param mixed $date
   *   The date in a format DateTimeImmutable understands, such as
   *   '2024-07-30T11:58:29+00:00'.
   *
   * @return int|null
   *   The timestamp, or NULL if the date is missing or invalid.
   */
  private function toTimestamp(mixed $date): ?int {
    if (!is_string($date) || $date === '') {
      return NULL;
    }
    try {
      return (new \DateTimeImmutable($date))->getTimestamp();
    }
    catch (\Exception) {
      return NULL;
    }
  }

}
