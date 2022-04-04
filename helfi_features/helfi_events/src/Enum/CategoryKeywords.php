<?php

namespace Drupal\helfi_events\Enum;

/**
 * Enum class CategoryKeywords.
 */
class CategoryKeywords {
  const CULTURE = [
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
  ];

  const MOVIE = [
    'yso:p1235',
  ];

  const INFLUENCE = [
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
  ];

  const MUSEUM = [
  // Museo.
    'matko:museo',
  // Museot.
    'yso:p4934',
  ];

  const SPORT = [
  // Liikunta.
    'yso:p916',
  // Urheilu.
    'yso:p965',
  ];

  const CAMPS = [
  // Leirit.
    'yso:p143',
  // kesäleirit.
    'yso:p21435',
  // Tiedeleirit.
    'yso:p22818',
  ];

  const TRIPS = [
  // Retket.
    'yso:p25261',
  // Retkeily.
    'yso:p1103',
  ];

  const WORKSHOPS = [
    // työpajat.
    'yso:p19245',
    'kulke:732',
  ];

  const MUSIC = [
    'yso:p1808',
  ];

  const FOOD = [
    'yso:p3670',
  ];

  const DANCE = [
    'yso:p1278',
  ];

  const THEATRE = [
    'yso:p2625',
  ];

  const CHILDREN = 'yso:p4354';

  /**
   * Enum class, prevent instantiating.
   */
  private function __construct() {}

}
