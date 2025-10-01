((Drupal) => {
  Drupal.HeaderIdInjector = {
    // List of reserved ids.
    reservedIds: [],

    // List of anchors.
    anchors: [],

    // Exclude elements from the injector that are not content:
    // e.g. TOC, sidebar, cookie compliance banner etc.
    exclusions: () => '' +
      ':not(.hide-from-table-of-contents *)',

    // List of heading tags with exclusions.
    titleComponents: (exclusions = Drupal.HeaderIdInjector.exclusions()) => [
      `h2${exclusions}`,
      `h3${exclusions}`,
      `h4${exclusions}`,
      `h5${exclusions}`,
      `h6${exclusions}`,
    ],

    // Find available ID for the anchor link.
    findAvailableId: (name, count) => {
      let newName = name;

      // Add postfix to the name if heading is not unique.
      if (count > 0) {
        newName += `-${count}`;
      }

      if (Drupal.HeaderIdInjector.reservedIds.includes(newName)) {
        return Drupal.HeaderIdInjector.findAvailableId(name, count + 1);
      }

      if (Drupal.HeaderIdInjector.anchors.includes(newName)) {
        // When reserved heading is visible on page, lets start counting from 2 instead of 1
        if (count === 0) {
          count += 1;
        }
        return Drupal.HeaderIdInjector.findAvailableId(name, count + 1);
      }
      return newName;
    },

    // Main languages.
    mainLanguages: () => ['en', 'fi', 'sv'],

    // Locale conversions.
    localeConversions: () => ({
      '0': '[°₀۰０]',
      '1': '[¹₁۱１]',
      '2': '[²₂۲２]',
      '3': '[³₃۳３]',
      '4': '[⁴₄۴٤４]',
      '5': '[⁵₅۵٥５]',
      '6': '[⁶₆۶٦６]',
      '7': '[⁷₇۷７]',
      '8': '[⁸₈۸８]',
      '9': '[⁹₉۹９]',
      'a': '[àáảãạăắằẳẵặâấầẩẫậāąåαάἀἁἂἃἄἅἆἇᾀᾁᾂᾃᾄᾅᾆᾇὰᾰᾱᾲᾳᾴᾶᾷаأအာါǻǎªაअاａä]',
      'aa': '[عआآ]',
      'ae': '[æǽ]',
      'ai': '[ऐ]',
      'b': '[бβبဗბｂब]',
      'c': '[çćčĉċｃ©]',
      'ch': '[чჩჭچ]',
      'd': '[ďðđƌȡɖɗᵭᶁᶑдδدضဍဒდｄᴅᴆ]',
      'dj': '[ђđ]',
      'dz': '[џძ]',
      'e': '[éèẻẽẹêếềểễệëēęěĕėεέἐἑἒἓἔἕὲеёэєəဧေဲეएإئｅ]',
      'ei': '[ऍ]',
      'f': '[фφفƒფｆ]',
      'g': '[ĝğġģгґγဂგگｇ]',
      'gh': '[غღ]',
      'gx': '[ĝ]',
      'h': '[ĥħηήحهဟှჰｈ]',
      'hx': '[ĥ]',
      'i': '[íìỉĩịîïīĭįıιίϊΐἰἱἲἳἴἵἶἷὶῐῑῒῖῗіїиဣိီည်ǐიइیｉi̇ϒ]',
      'ii': '[ई]',
      'ij': '[ĳ]',
      'j': '[ĵјჯجｊ]',
      'jx': '[ĵ]',
      'k': '[ķĸкκقكကკქکｋ]',
      'kh': '[хخხ]',
      'l': '[łľĺļŀлλلလლｌल]',
      'lj': '[љ]',
      'm': '[мμمမმｍ]',
      'n': '[ñńňņŉŋνнنနნｎ]',
      'nj': '[њ]',
      'o': '[óòỏõọôốồổỗộơớờởỡợøōőŏοὀὁὂὃὄὅὸόоوθိုǒǿºოओｏöө]',
      'oe': '[öœؤ]',
      'oi': '[ऑ]',
      'oii': '[ऒ]',
      'p': '[пπပპپｐ]',
      'ps': '[ψ]',
      'q': '[ყｑ]',
      'r': '[ŕřŗрρرრｒ]',
      's': '[śšşсσșςسصစſსｓŝ]',
      'sh': '[шშش]',
      'shch': '[щ]',
      'ss': '[ß]',
      'sx': '[ŝ]',
      't': '[ťţтτțتطဋတŧთტｔ]',
      'th': '[þϑثذظ]',
      'ts': '[цცწ]',
      'u': '[úùủũụưứừửữựûūůűŭųµуဉုူǔǖǘǚǜუउｕўü]',
      'ue': '[ü]',
      'uu': '[ऊ]',
      'v': '[вვϐｖ]',
      'w': '[ŵωώဝွｗ]',
      'x': '[χξｘ]',
      'y': '[ýỳỷỹỵÿŷйыυϋύΰيယｙῠῡὺ]',
      'ya': '[я]',
      'yu': '[ю]',
      'z': '[źžżзζزဇზｚ]',
      'zh': '[жჟژ]',
    }),
  };
})(Drupal);
