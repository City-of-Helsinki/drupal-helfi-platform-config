<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_platform_config\Unit\Plugin\EntityReferenceSelection;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\UnroutedUrlAssembler;
use Drupal\helfi_platform_config\MultisiteContentId;
use Drupal\helfi_platform_config\Plugin\EntityReferenceSelection\MultisiteContentSelection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests multisite content autocomplete labels.
 */
#[Group('helfi_platform_config')]
final class MultisiteContentSelectionTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * An entity id that can be parsed back out of an autocomplete value.
   */
  private const string ENTITY_ID = 'site_etusivu/entity:node/1:fi';

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('unrouted_url_assembler', new UnroutedUrlAssembler(new RequestStack(), $this->createMock(OutboundPathProcessorInterface::class)));
    \Drupal::setContainer($container);
  }

  /**
   * The suggestion label includes the external URL.
   */
  public function testLabelIncludesExternalUrl() : void {
    $entity = $this->entity();
    $entity->getExternalUrl()->willReturn(Url::fromUri('https://www.hel.fi/en/library'));

    $options = $this->selection($entity->reveal())->getReferenceableEntities('Lib');

    $this->assertSame(
      'Library (https://www.hel.fi/en/library)',
      $options['helfi_multisite_content'][self::ENTITY_ID],
    );
  }

  /**
   * A result without an external URL keeps the title label.
   */
  public function testLabelUnchangedWhenUrlIsMissing() : void {
    $entity = $this->entity();
    $entity->getExternalUrl()->willReturn(NULL);

    $options = $this->selection($entity->reveal())->getReferenceableEntities('Lib');

    $this->assertSame('Library', $options['helfi_multisite_content'][self::ENTITY_ID]);
  }

  /**
   * Builds an entity double with a title and bundle.
   *
   * @phpstan-return \Prophecy\Prophecy\ObjectProphecy<\Drupal\Tests\helfi_platform_config\Unit\Plugin\EntityReferenceSelection\ExternalUrlEntityInterface>
   */
  private function entity() : ObjectProphecy {
    $entity = $this->prophesize(ExternalUrlEntityInterface::class);
    $entity->bundle()->willReturn(MultisiteContentId::ENTITY_TYPE_ID);
    $entity->label()->willReturn('Library');
    return $entity;
  }

  /**
   * Builds the selection plugin around one matching entity.
   */
  private function selection(EntityInterface $entity) : MultisiteContentSelection {
    $query = $this->prophesize(QueryInterface::class);
    $query->accessCheck(TRUE)->willReturn($query->reveal());
    $query->condition('title', 'Lib', 'CONTAINS')->willReturn($query->reveal());
    $query->addTag(MultisiteContentId::ENTITY_TYPE_ID . '_access')->willReturn($query->reveal());
    $query->addTag('entity_reference')->willReturn($query->reveal());
    $query->addMetaData('entity_reference_selection_handler', Argument::type(MultisiteContentSelection::class))->willReturn($query->reveal());
    $query->execute()->willReturn([self::ENTITY_ID]);

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->getQuery()->willReturn($query->reveal());
    $storage->loadMultiple([self::ENTITY_ID])->willReturn([self::ENTITY_ID => $entity]);

    $entity_type = $this->prophesize(EntityTypeInterface::class);
    $entity_type->getKey('label')->willReturn('title');

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getDefinition(MultisiteContentId::ENTITY_TYPE_ID)->willReturn($entity_type->reveal());
    $entity_type_manager->getStorage(MultisiteContentId::ENTITY_TYPE_ID)->willReturn($storage->reveal());

    $entity_repository = $this->prophesize(EntityRepositoryInterface::class);
    $entity_repository->getTranslationFromContext($entity)->willReturn($entity);

    return new MultisiteContentSelection(
      ['target_type' => MultisiteContentId::ENTITY_TYPE_ID],
      'default:helfi_multisite_content',
      [],
      $entity_type_manager->reveal(),
      $this->prophesize(ModuleHandlerInterface::class)->reveal(),
      $this->prophesize(AccountInterface::class)->reveal(),
      $this->prophesize(EntityFieldManagerInterface::class)->reveal(),
      $this->prophesize(EntityTypeBundleInfoInterface::class)->reveal(),
      $entity_repository->reveal(),
    );
  }

}

/**
 * Test double for an entity that can provide an external URL.
 */
interface ExternalUrlEntityInterface extends EntityInterface {

  /**
   * Returns the external URL.
   */
  public function getExternalUrl() : ?Url;

}
