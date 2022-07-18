<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Validator;

use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use function GuzzleHttp\json_decode;

/**
 * Wrapper for JsonSchema validator.
 */
class ExternalMenuValidator {

  /**
   * Schema file contents.
   *
   * @var object
   */
  private array $schema;

  /**
   * JsonSchema validator.
   *
   * @var JsonSchema\Validator
   */
  private Validator $validator;

  /**
   * Construct.
   */
  public function __construct(
    private SchemaStorage $schemaStorage,
  ) {
    try {
      $this->schema = json_decode(file_get_contents(__DIR__ . '/../../assets/schema.json'));
    }
    catch(\Exception $exception) {
      throw new \Exception('Schema file not found: ' . $exception->getMessage());
    }
    $this->schemaStorage->addSchema('file://schema', $this->schema);
    $this->validator = new Validator(new Factory($this->schemaStorage));
  }

  /**
   * Validates JSON against the schema.
   *
   * @param array $json
   *   The json string to validate.
   */
  public function validate(array $json): bool {
    $this->validator->validate($json, $this->schema);

    if ($this->validator->isValid()) {
      return TRUE;
    }
    else {
      $error_string = '';
      foreach ($this->validator->getErrors() as $error) {
        $error_string .= sprintf('[%s] %s \n', $error['property'], $error['message']);
      }

      $this->logger->notice('Validation failed for external menu. Violations: \n' . $error_string);
      return FALSE;
    }
  }

}
