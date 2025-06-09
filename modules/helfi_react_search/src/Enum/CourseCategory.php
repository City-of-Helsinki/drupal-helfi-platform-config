<?php

declare(strict_types=1);

namespace Drupal\helfi_react_search\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Enum class EventCategoryKeywords.
 *
 * @see https://github.com/City-of-Helsinki/events-helsinki-monorepo/blob/main/apps/hobbies-helsinki/src/domain/search/eventSearch/constants.tsx
 */
enum CourseCategory: string implements EventListCategoryInterface {
  case Movie = 'movie_and_media';
  case Languages = 'languages';
  case Literature = 'literature';
  case ArtsAndCulture = 'arts_and_culture';
  case VisualArts = 'visual_arts';
  case Handicrafts = 'handicrafts';
  case Sport = 'sport';
  case Music = 'music';
  case Games = 'games';
  case Food = 'food';
  case Dance = 'dance';
  case Theatre = 'theatre';

  /**
   * {@inheritdoc}
   */
  public function keywords(): array {
    // These are copied from events-helsinki-monorepo.
    return match ($this) {
      self::Movie => [
      // Elokuva.
        'yso:p1235',
      // Elokuvat.
        'kulke:29',
      // Media.
        'yso:p16327',
      // Mediataide.
        'kulke:205',
      // Valokuva.
        'yso:p9731',
      // Valokuvaus.
        'kulke:87',
      // ?
        'yso:p1979',
      ],
      self::Languages => [
      // Kielet.
        'yso:p556',
      // Kieltenopetus.
        'yso:p38117',
      ],
      self::Literature => [
        // sanataide, kirjallisuus, sarjakuva.
        'yso:p8113',
        'yso:p7969',
        'kulke:81',
        'yso:p38773',
      ],
      self::ArtsAndCulture => [
        'yso:p2625',
        'yso:p27886',
        'yso:p2315',
        'yso:p16164',
        'yso:p9058',
        'kulke:51',
        'yso:p1235',
        'kulke:29',
        'yso:p16327',
        'kulke:205',
        'yso:p973',
        'yso:p2851',
        'yso:p1148',
        'yso:p38773',
        'yso:p695',
        'yso:p1808',
        'yso:p10871',
        'yso:p20421',
        'yso:p2969',
        'yso:p23171',
        'yso:p27962',
        'yso:p18718',
        'yso:p18434',
        'yso:p15521',
        'yso:p13408',
        'yso:p29932',
        'yso:p768',
        'yso:p2841',
        'yso:p6283',
        'yso:p1278',
        'yso:p10105',
        'yso:p3984',
        'yso:p25118',
        'yso:p10218',
        'yso:p21524',
        'yso:p37874',
        'yso:p1780',
      ],
      self::VisualArts => [
      // Sarjakuva.
        'kulke:81',
      // Sarjakuvat / comics.
        'yso:p1148',
      // Kuvataide / fine arts.
        'yso:p2739',
      // Sarjakuvataide / comic art.
        'yso:p38773',
      // Maalaustaide / painting (visual arts)
        'yso:p8883',
      // piirtäminen (taide) / drawing (artistic creation)
        'yso:p695',
      ],
      self::Handicrafts => [
        'yso:p4923',
        'yso:p485',
        'kulke:668',
        'yso:p8630',
      ],
      self::Sport => [
        'yso:p916',
        'kulke:710',
        'yso:p17018',
        'yso:p1963',
        'yso:p9824',
        'yso:p965',
        'yso:p6409',
        'yso:p8781',
        'yso:p26619',
        'yso:p13035',
        'yso:p2041',
      ],
      self::Music => [
        'yso:p1808',
        'yso:p10871',
        'yso:p20421',
        'yso:p2969',
        'yso:p23171',
        'yso:p27962',
        'yso:p18718',
        'yso:p18434',
        'yso:p15521',
        'yso:p13408',
        'yso:p29932',
        'yso:p768',
        'yso:p2841',
      ],
      self::Games => [
        'yso:p6062',
        'yso:p2758',
        'yso:p21628',
        'yso:p17281',
        'yso:p22610',
        'yso:p4295',
        'yso:p7990',
      ],
      self::Food => [
        'yso:p367',
        'yso:p5529',
        'yso:p28276',
      ],
      self::Dance => [
        'yso:p6283',
        'yso:p1278',
        'yso:p10105',
        'yso:p3984',
        'yso:p25118',
        'yso:p10218',
        'yso:p21524',
        'yso:p37874',
      ],
      self::Theatre => [
        'yso:p2625',
        'yso:p27886',
        'yso:p2315',
        'yso:p16164',
        'yso:p9058',
      ],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function translation(): TranslatableMarkup {
    return match ($this) {
      self::Movie => new TranslatableMarkup('Movies', [], ['context' => 'helfi_react_search']),
      self::Languages => new TranslatableMarkup('Languages', [], ['context' => 'helfi_react_search']),
      self::Literature => new TranslatableMarkup('Literature and literary art', [], ['context' => 'helfi_react_search']),
      self::ArtsAndCulture => new TranslatableMarkup('Arts and culture', [], ['context' => 'helfi_react_search']),
      self::VisualArts => new TranslatableMarkup('Visual arts', [], ['context' => 'helfi_react_search']),
      self::Handicrafts => new TranslatableMarkup('Crafts', [], ['context' => 'helfi_react_search']),
      self::Sport => new TranslatableMarkup('Sport', [], ['context' => 'helfi_react_search']),
      self::Music => new TranslatableMarkup('Music', [], ['context' => 'helfi_react_search']),
      self::Games => new TranslatableMarkup('Games', [], ['context' => 'helfi_react_search']),
      self::Food => new TranslatableMarkup('Food', [], ['context' => 'helfi_react_search']),
      self::Dance => new TranslatableMarkup('Dance', [], ['context' => 'helfi_react_search']),
      self::Theatre => new TranslatableMarkup('Theatre', [], ['context' => 'helfi_react_search']),
    };
  }

}
