<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_paragraphs_contact_card_listing\Unit;

use Drupal\helfi_paragraphs_contact_card_listing\SocialMediaServiceParserTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the SocialMediaServiceParserTrait.
 *
 * @group helfi_paragraphs_contact_card_listing
 */
class SocialMediaServiceParserTraitTest extends UnitTestCase {

  /**
   * A testable class using the trait.
   *
   * @var object
   */
  private $testClass;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an anonymous class that uses the trait for testing.
    $this->testClass = new class() {
      use SocialMediaServiceParserTrait;

      /**
       * {@inheritdoc}
       */
      public function publicProcessSocialMediaDomain(?string $social_media_link_uri): array {
        return $this->processSocialMediaDomain($social_media_link_uri);
      }

    };
  }

  /**
   * Provides data for testing social media domain parsing.
   *
   * @return array
   *   Returns test data as an array.
   */
  public static function socialMediaDataProvider(): array {
    return [
      ['https://www.facebook.com/user', 'Facebook', 'facebook', 'https://www.facebook.com/user'],
      ['https://twitter.com/user', 'X', 'twitter', 'https://twitter.com/user'],
      ['https://x.com/user', 'X', 'x', 'https://x.com/user'],
      ['https://instagram.com/user', 'Instagram', 'instagram', 'https://instagram.com/user'],
      ['https://linkedin.com/in/user', 'LinkedIn', 'linkedin', 'https://linkedin.com/in/user'],
      ['https://www.youtube.com/channel/xyz', 'YouTube', 'youtube', 'https://www.youtube.com/channel/xyz'],
      ['https://tiktok.com/@user', 'TikTok', 'tiktok', 'https://tiktok.com/@user'],
      ['https://www.test.hel.ninja/user', 'https://www.test.hel.ninja/user', 'link', 'https://www.test.hel.ninja/user'],
    ];
  }

  /**
   * Tests processing social media domain.
   */
  #[DataProvider('socialMediaDataProvider')]
  public function testProcessSocialMediaDomain(?string $input, ?string $expectedName, string $expectedIcon, ?string $expectedUrl): void {
    $result = $this->testClass->publicProcessSocialMediaDomain($input);

    $this->assertEquals($expectedName, $result['social_media_name']);
    $this->assertEquals($expectedIcon, $result['social_media_icon']);
    $this->assertEquals($expectedUrl, $result['social_media_url']);
  }

  /**
   * Tests that an exception is thrown for invalid URLs.
   */
  public function testProcessSocialMediaDomainThrowsException(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Invalid url.');

    $this->testClass->publicProcessSocialMediaDomain(NULL);
  }

}
