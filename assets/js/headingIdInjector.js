/**
 * Heading ID Injector
 *
 * This javascript injects unique ID-attributes to the assigned headings
 * built from the heading-elements text. This library should be active on
 * all except admin routes.
 */

((Drupal, once, drupalSettings) => {
  Drupal.HeadingIdInjector = {
    // List of reserved ids.
    reservedIds: [],

    // List of anchors.
    anchors: [],

    // Injected headings for the use of TOC.
    injectedHeadings: [],

    // Exclude elements from the injector that are not content:
    // e.g. TOC, sidebar, cookie compliance banner etc.
    exclusions: () => '' +
      ':not(.hide-from-table-of-contents *)',

    // List of heading tags with exclusions.
    titleComponents: (exclusions = Drupal.HeadingIdInjector.exclusions()) => [
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

      if (Drupal.HeadingIdInjector.reservedIds.includes(newName)) {
        return Drupal.HeadingIdInjector.findAvailableId(name, count + 1);
      }

      if (Drupal.HeadingIdInjector.anchors.includes(newName)) {
        // When reserved heading is visible on page, lets start counting from 2 instead of 1
        if (count === 0) {
          count += 1;
        }
        return Drupal.HeadingIdInjector.findAvailableId(name, count + 1);
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

    injectIds: (content) => {
      // Remove loading text and noscript element.
      let name = content.textContent
        .toLowerCase()
        .trim();

      // To ensure backwards compatibility, this is done only to "other" languages.
      if (!Drupal.HeadingIdInjector.mainLanguages().includes(drupalSettings.path.currentLanguage)) {
        Object.keys(Drupal.HeadingIdInjector.localeConversions()).forEach((swap) => {
          name = name.replace(new RegExp(Drupal.HeadingIdInjector.localeConversions()[swap], 'g'), swap);
        });
      }
      else {
        name = name
          .replace(/ä/gi, 'a')
          .replace(/ö/gi, 'o')
          .replace(/å/gi, 'a');
      }

      name = name
        // Replace any remaining non-word character including whitespace with '-'.
        // This leaves only characters matching [A-Za-z0-9-_] to the name.
        .replace(/\W/g, '-')
        // Use underscore at the end of the string: 'example-1' -> 'example_1'.
        .replace(/-(\d+)$/g, '_$1');

      let nodeName = content.nodeName.toLowerCase();
      if (nodeName === 'button') {
        nodeName = content.parentElement.nodeName.toLowerCase();
      }

      const anchorName = content.id
        ? content.id
        : Drupal.HeadingIdInjector.findAvailableId(name, 0);

      Drupal.HeadingIdInjector.anchors.push(anchorName);

      // Create anchor links.
      content.setAttribute('id', anchorName);
      content.setAttribute('tabindex', '-1');  // Set tabindex to -1 to avoid issues with screen readers.

      return {
        nodeName,
        anchorName,
      };
    },
  };

  // Heading ID injector.
  Drupal.behaviors.headingIdInjector = {
    attach: function attach(context) {
      // Prevent running multiple times on the main document.
      if (window.headingIdInjectorInitialized && context === document) {
        return;
      }

      // Find the main content wrapper element.
      const mainContent = context.querySelector('main.layout-main-wrapper');

      // If no main content exists, stop execution.
      if (!mainContent) {
        return;
      }

      // Collect all elements that already have an ID to avoid conflicts.
      const reservedElems = context.querySelectorAll('[id]');
      reservedElems.forEach((elem) => {
        Drupal.HeadingIdInjector.reservedIds.push(elem.id);
      });

      // Inject IDs into headings and store info about each injected heading.
      once('heading-id-injector', Drupal.HeadingIdInjector.titleComponents().join(','), mainContent)
        .forEach((content) => {
          const { nodeName, anchorName } = Drupal.HeadingIdInjector.injectIds(content);
          Drupal.HeadingIdInjector.injectedHeadings.push({ nodeName, anchorName, content });
        });

      // Mark as initialized so it won't re-run unnecessarily.
      window.headingIdInjectorInitialized = true;
    }
  };

})(Drupal, once, drupalSettings);
