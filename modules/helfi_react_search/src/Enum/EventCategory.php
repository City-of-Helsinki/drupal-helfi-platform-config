<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enum class EventCategory.
 */
enum EventCategory: string implements EventListCategoryInterface {
  case Movie = 'movie';
  case Culture = 'culture';
  case Sport = 'sport';
  case Nature = 'nature';
  case Museum = 'museum';
  case Music = 'music';
  case Influence = 'influence';
  case Food = 'food';
  case Dance = 'dance';
  case Theatre = 'theatre';

  /**
   * {@inheritdoc}
   */
  public function keywords(): array {
    return match ($this) {
      self::Culture => [
        // Teatteri.
        'kulke:33',
        // Sirkus.
        'kulke:51',
        // Elokuva ja media.
        'kulke:205',
        // Teatteri ja sirkus.
        'kulke:351',
        // Teatteri.
        'matko:teatteri',
        // Cultural events.
        'yso:p360',
        // Films.
        'yso:p1235',
        // Dance (performing arts)
        'yso:p1278',
        // Music.
        'yso:p1808',
        // In Finnish teatteritaide, "theatre arts".
        'yso:p2625',
        // Fine arts.
        'yso:p2739',
        // Performing arts.
        'yso:p2850',
        // Art.
        'yso:p2851',
        // Museums.
        'yso:p4934',
        // Exhibitions.
        'yso:p5121',
        // Art exhibitions.
        'yso:p6889',
        // Literary art.
        'yso:p7969',
        // Literature.
        'yso:p8113',
        // Art museums.
        'yso:p8144',
        // Modern art.
        'yso:p9592',
        // Contemporary art.
        'yso:p9593',
        // Contemporary dance.
        'yso:p10105',
        // Cinema (art forms)
        'yso:p16327',
      ],
      self::Movie=> [
        'yso:p1235',
      ],
      self::Influence => [
        // Vaikuttaminen.
        'yso:p1657',
        // Demokratia.
        'yso:p742',
        // Osallisuus.
        'yso:p5164',
        // Kaavoitus.
        'yso:p8268',
        // Asemakaavoitus.
        'yso:p15882',
        // Kaupunkipolitiikka.
        'yso:p15292',
      ],
      self::Museum => [
        // Museo.
        'matko:museo',
        // Museot.
        'yso:p4934',
      ],
      self::Sport => [
        // Liikunta.
        'yso:p916',
        // Urheilu.
        'yso:p965',
      ],
      self::Music => [
        'yso:p1808',
      ],
      self::Food => [
        'yso:p3670',
      ],
      self::Dance => [
        'yso:p1278',
      ],
      self::Theatre => [
        'yso:p2625',
      ],
      self::Nature => [
        'yso:p2771',
      ],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function translation(): TranslatableMarkup {
    return match ($this) {
      self::Movie => new TranslatableMarkup('Movies', [], ['context' => 'event list category']),
      self::Culture => new TranslatableMarkup('Arts and culture', [], ['context' => 'event list category']),
      self::Sport => new TranslatableMarkup('Exercise and sports', [], ['context' => 'event list category']),
      self::Nature => new TranslatableMarkup('Nature and outdoor activity', [], ['context' => 'event list category']),
      self::Museum => new TranslatableMarkup('Museums', [], ['context' => 'event list category']),
      self::Music => new TranslatableMarkup('Music', [], ['context' => 'event list category']),
      self::Influence => new TranslatableMarkup('Participate and influence', [], ['context' => 'event list category']),
      self::Food => new TranslatableMarkup('Food', [], ['context' => 'event list category']),
      self::Dance => new TranslatableMarkup('Dance', [], ['context' => 'event list category']),
      self::Theatre => new TranslatableMarkup('Theatre', [], ['context' => 'event list category']),
    };
  }

}
