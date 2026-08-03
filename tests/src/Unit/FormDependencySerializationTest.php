<?php

declare(strict_types=1);

namespace Drupal\Tests\grok_doc\Unit;

use Drupal\grok_doc\Form\AddDocumentForm;
use Drupal\grok_doc\Form\BulkImportForm;
use Drupal\grok_doc\Form\GrokCollectionDeleteForm;
use Drupal\grok_doc\Form\GrokCollectionForm;
use Drupal\grok_doc\Form\GrokDocSettingsForm;
use Drupal\grok_doc\Form\ProcessQueueForm;
use Drupal\grok_doc\Form\RemoteCollectionsForm;
use PHPUnit\Framework\TestCase;

/**
 * Protects injected form services across Drupal AJAX serialization.
 */
final class FormDependencySerializationTest extends TestCase {

  /**
   * Ensures promoted dependencies are visible to FormBase's serializer.
   */
  public function testInjectedPropertiesAreSerializableByFormBase(): void {
    $classes = [
      AddDocumentForm::class,
      BulkImportForm::class,
      GrokCollectionDeleteForm::class,
      GrokCollectionForm::class,
      GrokDocSettingsForm::class,
      ProcessQueueForm::class,
      RemoteCollectionsForm::class,
    ];

    foreach ($classes as $class) {
      $reflection = new \ReflectionClass($class);
      foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
        if (!$parameter->isPromoted()) {
          continue;
        }
        $property = $reflection->getProperty($parameter->getName());
        self::assertTrue($property->isProtected(), $class . '::$' . $property->getName() . ' must be protected so DependencySerializationTrait can inspect it.');
        self::assertFalse($property->isReadOnly(), $class . '::$' . $property->getName() . ' must be writable during __wakeup().');
      }
    }
  }

}
