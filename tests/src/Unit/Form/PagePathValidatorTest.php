<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_platform_config\Form\PagePathValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests page path pattern validation.
 */
#[Group('helfi_platform_config')]
final class PagePathValidatorTest extends UnitTestCase {

  /**
   * Tests paths that are accepted.
   */
  #[DataProvider('validPaths')]
  public function testValidPaths(string $value): void {
    $this->assertNull($this->validate($this->validatorWithDefaultPrefixes(), $value));
  }

  /**
   * Data provider for testValidPaths().
   *
   * @return array<string, array{string}>
   *   Valid textarea values.
   */
  public static function validPaths(): array {
    return [
      'empty' => [''],
      'blank lines' => ["\n  \n"],
      'front page' => ['<front>'],
      'front page with surrounding space' => ["  <front>  \r"],
      'exact path' => ['/feedback'],
      'wildcard subtree' => ['/feedback/*'],
      'root path' => ['/'],
      'several valid lines' => ["<front>\n/feedback\n/user/*"],
      'prefix only later in the path' => ['/feedback/fi'],
      'internal path' => ['/node/1'],
      'segment that only starts with a prefix' => ['/fi-page'],
    ];
  }

  /**
   * Tests that valid lines are stored without surrounding whitespace.
   *
   * @param string $value
   *   The submitted textarea value.
   * @param string $expected
   *   The value written back to form state.
   */
  #[DataProvider('trimmedPaths')]
  public function testValidPathsAreStoredTrimmed(string $value, string $expected): void {
    $stored = NULL;
    $this->assertNull($this->validate($this->validatorWithDefaultPrefixes(), $value, $stored));
    $this->assertSame($expected, $stored);
  }

  /**
   * Data provider for testValidPathsAreStoredTrimmed().
   *
   * @return array<string, array{string, string}>
   *   Submitted values and the expected stored value.
   */
  public static function trimmedPaths(): array {
    return [
      'already clean' => ["/feedback\n/user/*", "/feedback\n/user/*"],
      'surrounding space' => ["  /feedback  \n\t/user/*\t", "/feedback\n/user/*"],
      'front page with carriage return' => ["  <front>  \r", '<front>'],
      'windows line endings' => ["  /feedback  \r\n  <front>  \r\n", "/feedback\n<front>\n"],
      'blank lines stay blank' => ["\n  \n/feedback\n", "\n\n/feedback\n"],
      'whitespace only' => ['   ', ''],
    ];
  }

  /**
   * Tests that an invalid value is left unchanged.
   */
  public function testInvalidValueIsNotNormalized(): void {
    $stored = NULL;
    $error = $this->validate($this->validatorWithDefaultPrefixes(), "  /feedback  \nnope", $stored);

    $this->assertInstanceOf(TranslatableMarkup::class, $error);
    $this->assertSame('The path %path requires a leading forward slash.', $error->getUntranslatedString());
    $this->assertSame(['%path' => 'nope'], $error->getArguments());
    $this->assertNull($stored);
  }

  /**
   * Tests paths that are rejected.
   *
   * @param string $value
   *   The textarea value.
   * @param string $message
   *   The expected untranslated error message.
   * @param array<string, string> $arguments
   *   The expected placeholder arguments.
   */
  #[DataProvider('invalidPaths')]
  public function testInvalidPaths(string $value, string $message, array $arguments): void {
    $error = $this->validate($this->validatorWithDefaultPrefixes(), $value);

    $this->assertInstanceOf(TranslatableMarkup::class, $error);
    $this->assertSame($message, $error->getUntranslatedString());
    $this->assertSame($arguments, $error->getArguments());
  }

  /**
   * Data provider for testInvalidPaths().
   *
   * @return array<string, array{string, string, array<string, string>}>
   *   Invalid values with the expected error.
   */
  public static function invalidPaths(): array {
    $slash = 'The path %path requires a leading forward slash.';
    $prefix = 'The path %path includes a language or site prefix. Use %suggestion instead.';
    $everything = 'The path %path matches every page. Leave the field empty instead.';

    return [
      'missing slash' => [
        'feedback',
        $slash,
        ['%path' => 'feedback'],
      ],
      'lone wildcard' => [
        '*',
        $everything,
        ['%path' => '*'],
      ],
      'root wildcard' => [
        '/*',
        $everything,
        ['%path' => '/*'],
      ],
      'language prefix' => [
        '/fi/feedback',
        $prefix,
        ['%path' => '/fi/feedback', '%suggestion' => '/feedback'],
      ],
      'language prefix case' => [
        '/EN/news',
        $prefix,
        ['%path' => '/EN/news', '%suggestion' => '/news'],
      ],
      'site prefix' => [
        '/etusivu/feedback',
        $prefix,
        ['%path' => '/etusivu/feedback', '%suggestion' => '/feedback'],
      ],
      'language then site prefix' => [
        '/fi/etusivu/feedback/*',
        $prefix,
        ['%path' => '/fi/etusivu/feedback/*', '%suggestion' => '/feedback/*'],
      ],
      'site then language prefix' => [
        '/etusivu/sv/news',
        $prefix,
        ['%path' => '/etusivu/sv/news', '%suggestion' => '/news'],
      ],
      'complex language prefix' => [
        '/ZH-HANS/page',
        $prefix,
        ['%path' => '/ZH-HANS/page', '%suggestion' => '/page'],
      ],
      'language prefix only' => [
        '/fi',
        $prefix,
        ['%path' => '/fi', '%suggestion' => '<front>'],
      ],
      'site prefix only' => [
        '/etusivu/',
        $prefix,
        ['%path' => '/etusivu/', '%suggestion' => '<front>'],
      ],
      'repeated prefix' => [
        '/fi/fi/page',
        $prefix,
        ['%path' => '/fi/fi/page', '%suggestion' => '/page'],
      ],
      'first invalid line stops validation' => [
        "/feedback\nnope\n/fi/also-bad",
        $slash,
        ['%path' => 'nope'],
      ],
    ];
  }

  /**
   * Tests that an empty value does not read configuration.
   */
  public function testEmptyValueSkipsConfiguration(): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects($this->never())->method('get');

    $this->assertNull($this->validate(new PagePathValidator($configFactory), ''));
  }

  /**
   * Tests that a non-string value is ignored.
   */
  public function testNonStringValueIsIgnored(): void {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects($this->never())->method('get');

    $this->assertNull($this->validate(new PagePathValidator($configFactory), ['/fi/page']));
  }

  /**
   * Tests that missing or non-string prefix configuration is ignored.
   */
  public function testUnusablePrefixConfigurationIsIgnored(): void {
    $validator = $this->validator(NULL, [
      'counted' => 1,
      'blank' => '  ',
      'language' => '/EN/',
    ]);

    $this->assertNull($this->validate($validator, '/fi/page'));

    $error = $this->validate($validator, '/en/page');
    $this->assertInstanceOf(TranslatableMarkup::class, $error);
    $this->assertSame(
      'The path %path includes a language or site prefix. Use %suggestion instead.',
      $error->getUntranslatedString(),
    );
    $this->assertSame(
      ['%path' => '/en/page', '%suggestion' => '/page'],
      $error->getArguments(),
    );
  }

  /**
   * Validates a textarea value.
   *
   * @param \Drupal\helfi_platform_config\Form\PagePathValidator $validator
   *   The validator under test.
   * @param mixed $value
   *   The submitted textarea value.
   * @param mixed $stored
   *   The value written back to form state, or NULL when it is unchanged.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The recorded error, if any.
   */
  private function validate(PagePathValidator $validator, mixed $value, mixed &$stored = NULL): ?TranslatableMarkup {
    $element = [
      '#parents' => ['field_page_paths', 0, 'value'],
      '#value' => $value,
    ];
    $formState = new FormState();
    $completeForm = [];
    $validator->validate($element, $formState, $completeForm);
    $stored = $formState->getValue(['field_page_paths', 0, 'value']);
    $errors = $formState->getErrors();
    $error = $errors['field_page_paths][0][value'] ?? NULL;
    return $error instanceof TranslatableMarkup ? $error : NULL;
  }

  /**
   * Builds a validator with the usual language and site prefixes.
   *
   * @return \Drupal\helfi_platform_config\Form\PagePathValidator
   *   The validator.
   */
  private function validatorWithDefaultPrefixes(): PagePathValidator {
    return $this->validator(
      [
        'fi' => 'fi',
        'sv' => 'sv',
        'en' => 'en',
        'zh-hans' => 'zh-hans',
      ],
      ['zxx' => 'etusivu'],
    );
  }

  /**
   * Builds a validator for the given prefix configuration.
   *
   * @param array<string, mixed>|null $languagePrefixes
   *   Language negotiation URL prefixes.
   * @param array<string, mixed>|null $sitePrefixes
   *   Site instance prefixes from helfi_proxy.
   *
   * @return \Drupal\helfi_platform_config\Form\PagePathValidator
   *   The validator.
   */
  private function validator(?array $languagePrefixes, ?array $sitePrefixes): PagePathValidator {
    $language = $this->createMock(ImmutableConfig::class);
    $language->method('get')->with('url.prefixes')->willReturn($languagePrefixes);
    $site = $this->createMock(ImmutableConfig::class);
    $site->method('get')->with('prefixes')->willReturn($sitePrefixes);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnCallback(
      static function (string $name) use ($language, $site): ImmutableConfig {
        return match ($name) {
          'language.negotiation' => $language,
          'helfi_proxy.settings' => $site,
          default => throw new \InvalidArgumentException(sprintf(
            'Unexpected config name "%s".',
            $name,
          )),
        };
      },
    );

    return new PagePathValidator($configFactory);
  }

}
