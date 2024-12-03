<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config\Plugin\Block;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Template\Attribute;
use Drupal\social_media\Event\SocialMediaEvent;
use Drupal\social_media\Plugin\Block\SocialSharingBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'SocialMediaSharingBlock' block.
 *
 * @todo Rewrite the block. Get rid of social_media module.
 *
 * @Block(
 *  id = "helfi_platform_config_social_sharing_block",
 *  admin_label = @Translation("Social Media Sharing block"),
 * )
 */
class SocialMediaSharingBlock extends SocialSharingBlock {

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() : array {

    $build = [];

    $social_media = $this->moduleHandler->getModule('social_media');

    if (!$social_media) {
      return $build;
    }

    $library = ['social_media/basic'];
    $settings = [];

    $elements = [];
    $social_medias = $this->configFactory
      ->get('social_media.settings')
      ->get('social_media');

    // Call pre_execute event before doing anything.
    $event = new SocialMediaEvent($social_medias);
    $this->eventDispatcher->dispatch($event, 'social_media.pre_execute');
    $social_medias = $event->getElement();

    $social_medias = $this->sortSocialMedias($social_medias);
    foreach ($social_medias as $name => $social_media) {

      // Replace api url with different link.
      if ($name == "email" && isset($social_media['enable_forward']) && $social_media['enable_forward']) {
        $social_media['api_url'] = str_replace('mailto:', '/social-media-forward', $social_media['api_url']);
        $social_media['api_url'] .= '&destination=' . $this->currentPath->getPath();
        if (isset($social_media['show_forward']) && $social_media['show_forward'] == 1) {
          $library[] = 'core/drupal.dialog.ajax';
        }
      }

      if ($social_media['enable'] == 1 && !empty($social_media['api_url'])) {
        $elements[$name]['text'] = $social_media['text'];
        $elements[$name]['api'] = new Attribute([$social_media['api_event'] => trim($this->token->replace($social_media['api_url']))]);

        if (isset($social_media['library']) && !empty($social_media['library'])) {
          $library[] = $social_media['library'];
        }
        if (isset($social_media['attributes']) && !empty($social_media['attributes'])) {
          $elements[$name]['attr'] = $this->socialMediaConvertAttributes($social_media['attributes']);
        }
        if (isset($social_media['drupalSettings']) && !empty($social_media['drupalSettings'])) {
          $settings['social_media'] = $this->socialMediaConvertDrupalSettings($social_media['drupalSettings']);
        }

        if (isset($social_media['default_img']) && $social_media['default_img']) {
          $elements[$name]['img'] = $this->fileUrlGenerator
            ->generate("{$social_media->getPath()}/icons/$name.svg")
            ->toString(TRUE)->getGeneratedUrl();
        }
        elseif (!empty($social_media['img'])) {
          $elements[$name]['img'] = $social_media['img'];
        }

        if (isset($social_media['enable_forward']) && $social_media['enable_forward']) {
          if (isset($social_media['show_forward']) && $social_media['show_forward'] == 1) {
            $elements[$name]['forward_dialog'] = $social_media['show_forward'];
          }
        }
      }
    }

    $event = new SocialMediaEvent($elements);
    $this->eventDispatcher->dispatch($event, 'social_media.pre_render');
    $elements = $event->getElement();

    $build['social_sharing_block'] = [
      '#theme' => 'social_media_links',
      '#elements' => $elements,
      '#attached' => [
        'library' => $library,
        'drupalSettings' => $settings,
      ],
      '#cache' => [
        'tags' => [
          'social_media:' . $this->currentPath->getPath(),
        ],
        'contexts' => [
          'url',
        ],
      ],
    ];

    return $build;
  }

}
