<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Traits;

use Drupal\helfi_api_base\Azure\PubSub\PubSubClientFactoryInterface;
use Drupal\helfi_api_base\Azure\PubSub\PubSubManager;
use Drupal\helfi_api_base\Azure\PubSub\Settings;
use Psr\Log\NullLogger;
use WebSocket\Client;
use WebSocket\Message\Text;

/**
 * A real PubSub manager whose websocket client records the frames.
 *
 * @phpstan-require-extends \Drupal\KernelTests\KernelTestBase
 */
trait PubSubRecorderTrait {

  /**
   * Frames sent to the PubSub service, decoded.
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $sentFrames = [];

  /**
   * Builds a PubSub manager that records sent frames.
   *
   * @param list<\WebSocket\Message\Text> $received
   *   Frames the PubSub service delivers, in order.
   * @param \Drupal\helfi_api_base\Azure\PubSub\Settings|null $settings
   *   The PubSub settings. Defaults to a configured connection.
   */
  protected function pubSubManager(array $received = [], ?Settings $settings = NULL): PubSubManager {
    $client = $this->createMock(Client::class);
    $client->method('text')->willReturnCallback(function (string $frame): Text {
      $this->sentFrames[] = json_decode($frame, TRUE, flags: JSON_THROW_ON_ERROR);
      return new Text($frame);
    });
    $client->method('receive')->willReturnOnConsecutiveCalls(
      new Text('{"type":"event","event":"connected"}'),
      ...array_values($received),
    );

    $clientFactory = $this->createMock(PubSubClientFactoryInterface::class);
    $clientFactory->method('create')->willReturn($client);

    return new PubSubManager(
      $clientFactory,
      $this->container->get('event_dispatcher'),
      $this->container->get('datetime.time'),
      $settings ?? new Settings('local', 'shared', 'localhost', ['123']),
      new NullLogger(),
    );
  }

  /**
   * The data of every message sent to the PubSub group, without timestamps.
   *
   * @return array<int, array<string, mixed>>
   *   The message data, in sending order.
   */
  protected function sentMessages(): array {
    $messages = [];
    foreach ($this->sentFrames as $frame) {
      if (($frame['type'] ?? NULL) === 'sendToGroup') {
        $data = $frame['data'];
        unset($data['timestamp']);
        $messages[] = $data;
      }
    }
    return $messages;
  }

  /**
   * A frame the PubSub service delivers to group members.
   *
   * @param array<string, mixed> $data
   *   The message data, as given to sendMessage().
   */
  protected function deliveredFrame(array $data): Text {
    return new Text(json_encode([
      'type' => 'message',
      'from' => 'group',
      'group' => 'shared',
      'dataType' => 'json',
      'data' => $data,
    ], JSON_THROW_ON_ERROR));
  }

}
