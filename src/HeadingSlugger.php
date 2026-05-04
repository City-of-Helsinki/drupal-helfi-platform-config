<?php

declare(strict_types=1);

namespace Drupal\helfi_platform_config;

/**
 * Generates heading anchor slugs that attempts to match headingIdInjector.js.
 */
final class HeadingSlugger {

  /**
   * Main languages use simpler character mapping for backwards compatability.
   */
  private const array MAIN_LANGUAGES = ['en', 'fi', 'sv'];

  /**
   * Character classes ported from headingIdInjector.js.
   */
  private const array LOCALE_CONVERSIONS = [
    '0' => '[°₀۰０]',
    '1' => '[¹₁۱１]',
    '2' => '[²₂۲２]',
    '3' => '[³₃۳３]',
    '4' => '[⁴₄۴٤４]',
    '5' => '[⁵₅۵٥５]',
    '6' => '[⁶₆۶٦６]',
    '7' => '[⁷₇۷７]',
    '8' => '[⁸₈۸８]',
    '9' => '[⁹₉۹９]',
    'a' => '[àáảãạăắằẳẵặâấầẩẫậāąåαάἀἁἂἃἄἅἆἇᾀᾁᾂᾃᾄᾅᾆᾇὰᾰᾱᾲᾳᾴᾶᾷаأအာါǻǎªაअاａä]',
    'aa' => '[عआآ]',
    'ae' => '[æǽ]',
    'ai' => '[ऐ]',
    'b' => '[бβبဗბｂब]',
    'c' => '[çćčĉċｃ©]',
    'ch' => '[чჩჭچ]',
    'd' => '[ďðđƌȡɖɗᵭᶁᶑдδدضဍဒდｄᴅᴆ]',
    'dj' => '[ђđ]',
    'dz' => '[џძ]',
    'e' => '[éèẻẽẹêếềểễệëēęěĕėεέἐἑἒἓἔἕὲеёэєəဧေဲეएإئｅ]',
    'ei' => '[ऍ]',
    'f' => '[фφفƒფｆ]',
    'g' => '[ĝğġģгґγဂგگｇ]',
    'gh' => '[غღ]',
    'gx' => '[ĝ]',
    'h' => '[ĥħηήحهဟှჰｈ]',
    'hx' => '[ĥ]',
    'i' => '[íìỉĩịîïīĭįıιίϊΐἰἱἲἳἴἵἶἷὶῐῑῒῖῗіїиဣိီည်ǐიइیｉi̇ϒ]',
    'ii' => '[ई]',
    'ij' => '[ĳ]',
    'j' => '[ĵјჯجｊ]',
    'jx' => '[ĵ]',
    'k' => '[ķĸкκقكကკქکｋ]',
    'kh' => '[хخხ]',
    'l' => '[łľĺļŀлλلလლｌल]',
    'lj' => '[љ]',
    'm' => '[мμمမმｍ]',
    'n' => '[ñńňņŉŋνнنနნｎ]',
    'nj' => '[њ]',
    'o' => '[óòỏõọôốồổỗộơớờởỡợøōőŏοὀὁὂὃὄὅὸόоوθိုǒǿºოओｏöө]',
    'oe' => '[öœؤ]',
    'oi' => '[ऑ]',
    'oii' => '[ऒ]',
    'p' => '[пπပპپｐ]',
    'ps' => '[ψ]',
    'q' => '[ყｑ]',
    'r' => '[ŕřŗрρرრｒ]',
    's' => '[śšşсσșςسصစſსｓŝ]',
    'sh' => '[шშش]',
    'shch' => '[щ]',
    'ss' => '[ß]',
    'sx' => '[ŝ]',
    't' => '[ťţтτțتطဋတŧთტｔ]',
    'th' => '[þϑثذظ]',
    'ts' => '[цცწ]',
    'u' => '[úùủũụưứừửữựûūůűŭųµуဉုူǔǖǘǚǜუउｕўü]',
    'ue' => '[ü]',
    'uu' => '[ऊ]',
    'v' => '[вვϐｖ]',
    'w' => '[ŵωώဝွｗ]',
    'x' => '[χξｘ]',
    'y' => '[ýỳỷỹỵÿŷйыυϋύΰيယｙῠῡὺ]',
    'ya' => '[я]',
    'yu' => '[ю]',
    'z' => '[źžżзζزဇზｚ]',
    'zh' => '[жჟژ]',
  ];

  /**
   * Slugs already issued by this instance.
   *
   * @phpstan-var array<string, true>
   */
  private array $issued = [];

  /**
   * IDs already present on the page.
   *
   * @var array<string, true>
   */
  private array $reserved;

  /**
   * Constructs a new instance.
   *
   * @param string $langcode
   *   Language code.
   * @param string[] $reservedIds
   *   IDs already present on the page that the slug must not collide with.
   */
  public function __construct(
    private readonly string $langcode,
    array $reservedIds = [],
  ) {
    $this->reserved = array_fill_keys($reservedIds, TRUE);
  }

  /**
   * Generate a unique fragment for the given heading text.
   *
   * @param string $headingText
   *   Raw heading text.
   *
   * @return string
   *   The slug, with collision suffixes applied if needed.
   */
  public function slug(string $headingText): string {
    $name = mb_trim(mb_strtolower($headingText));

    if ($name === '') {
      return '';
    }

    $name = $this->transliterate($name);

    // Replace any non-ASCII-word character with '-'. Matches JS /\W/g (which
    // ignores Unicode), but stays Unicode-safe by enumerating ASCII directly.
    $name = preg_replace('/[^A-Za-z0-9_]/u', '-', $name) ?? $name;

    // Trailing -<digits> becomes _<digits> ('example-1' to 'example_1').
    $name = preg_replace('/-(\d+)$/', '_$1', $name) ?? $name;

    $available = $this->findAvailable($name);
    $this->issued[$available] = TRUE;
    return $available;
  }

  /**
   * Apply the locale conversion table.
   */
  private function transliterate(string $name): string {
    if (in_array($this->langcode, self::MAIN_LANGUAGES, TRUE)) {
      return strtr($name, ['ä' => 'a', 'ö' => 'o', 'å' => 'a']);
    }

    foreach (self::LOCALE_CONVERSIONS as $replacement => $pattern) {
      $name = preg_replace('/' . $pattern . '/u', (string) $replacement, $name) ?? $name;
    }
    return $name;
  }

  /**
   * Finds an available slug.
   */
  private function findAvailable(string $name, int $count = 0): string {
    $candidate = $count > 0 ? $name . '-' . $count : $name;

    if (isset($this->reserved[$candidate])) {
      return $this->findAvailable($name, $count + 1);
    }

    if (isset($this->issued[$candidate])) {
      if ($count === 0) {
        $count = 1;
      }
      return $this->findAvailable($name, $count + 1);
    }

    return $candidate;
  }

}
