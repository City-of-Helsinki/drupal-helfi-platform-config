<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\EntityVersionMatcher;
use Drupal\helfi_tpr\Entity\Service;

/**
 * Provides a 'SidebarContentBlock' block.
 */
#[Block(
  id: "sidebar_content_block",
  admin_label: new TranslatableMarkup("Sidebar content block"),
)]
class SidebarContentBlock extends ContentBlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build['sidebar_content'] = [
      '#theme' => 'sidebar_content_block',
      '#title' => $this->t('Sidebar content block'),
    ];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    ['entity' => $entity, 'entity_version' => $entity_version] = $this->getCurrentEntityVersion();

    // Add the sidebar content paragraphs to render array.
    if (
      $entity instanceof ContentEntityInterface &&
      $entity->hasField('field_sidebar_content')
    ) {
      $build['sidebar_content'] = $build['sidebar_content'] + [
        '#is_revision' => $entity_version == EntityVersionMatcher::ENTITY_VERSION_REVISION,
        '#paragraphs' => $entity->get('field_sidebar_content'),
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $build;
  }

}
