<?php

namespace Drupal\helfi_events\Enum;

class CategoryKeywords {
  const CULTURE = [
    'kulke:33', // Teatteri
    'kulke:51', // Sirkus
    'kulke:205', // Elokuva ja media
    'kulke:351', // Teatteri ja sirkus
    'matko:teatteri', // teatteri
    'yso:p360', // cultural events
    'yso:p1235', // films
    'yso:p1278', // dance (performing arts)
    'yso:p1808', // music
    'yso:p2625', // in Finnish teatteritaide, "theatre arts"
    'yso:p2739', // fine arts
    'yso:p2850', // performing arts
    'yso:p2851', // art
    'yso:p4934', // museums
    'yso:p5121', // exhibitions
    'yso:p6889', // art exhibitions
    'yso:p7969', // literary art
    'yso:p8113', // literature
    'yso:p8144', // art museums
    'yso:p9592', // modern art
    'yso:p9593', // contemporary art
    'yso:p10105', // contemporary dance
    'yso:p16327', // cinema (art forms)
  ];

  const MOVIE = [
    'yso:p1235'
  ];

  const INFLUENCE = [
    'yso:p1657', // Vaikuttaminen
    'yso:p742', // Demokratia
    'yso:p5164', // Osallisuus
    'yso:p8268', // Kaavoitus
    'yso:p15882', // Asemakaavoitus
    'yso:p15292', // Kaupunkipolitiikka
  ];

  const MUSEUM = [
    'matko:museo', // Museo
    'yso:p4934', // Museot
  ];
  
  const SPORT = [
    'yso:p916', // Liikunta
    'yso:p965', // Urheilu
  ];
  
  const CAMPS = [
    'yso:p143', //leirit
    'yso:p21435', //kesäleirit
    'yso:p22818', //tiedeleirit
  ];
  
  const TRIPS = [
    'yso:p25261', //retket
    'yso:p1103', //retkeily
  ];
  
  const WORKSHOPS = [
    //työpajat
    'yso:p19245',
    'kulke:732',
  ];

  const MUSIC = [
    'yso:p1808'
  ];

  const FOOD = [
    'yso:p3670'
  ];

  const DANCE = [
    'yso:p1278'
  ];

  const THEATRE = [
    'yso:p2625'
  ];

  // Enum class, prevent instantiating
  private function __construct() {}
}
