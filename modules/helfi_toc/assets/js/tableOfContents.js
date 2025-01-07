'use strict';

((Drupal, once, drupalSettings) => {

  // Global table of contents object.
  Drupal.tableOfContents = {

    // List of reserved ids.
    reservedIds: [],

    // List of anchors.
    anchors: [],

    // Exclude elements from TOC that are not content:
    // e.g. TOC, sidebar, cookie compliance banner etc.
    exclusions: () => {
      return '' +
      ':not(.hide-from-table-of-contents *)'
    },

    // List of heading tags with exclusions.
    titleComponents: (exclusions = Drupal.tableOfContents.exclusions()) => {
      return [
        `h2${exclusions}`,
        `h3${exclusions}`,
        `h4${exclusions}`,
        `h5${exclusions}`,
        `h6${exclusions}`,
      ];
    },

    // Find available ID for the anchor link.
    findAvailableId: (name, count) => {
      let newName = name;

      // Add postfix to the name if heading is not unique.
      if (count > 0) {
        newName += `-${count}`;
      }

      if (Drupal.tableOfContents.reservedIds.includes(newName)) {
        return Drupal.tableOfContents.findAvailableId(name, count + 1);
      }

      if (Drupal.tableOfContents.anchors.includes(newName)) {
        // When reserved heading is visible on page, lets start counting from 2 instead of 1
        if (count === 0) {
          count += 1;
        }
        return Drupal.tableOfContents.findAvailableId(name,count + 1);
      }
      return newName;
    },

    // Main languages.
    mainLanguages: () => {
      return ['en', 'fi', 'sv'];
    },

    // Locale conversions.
    localeConversions: () => {
      return {
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
      };
    },

    // A function to create table of content elements.
    createTableOfContentElements: (content) => {
      // Remove loading text and noscript element.
      let name = content.textContent
        .toLowerCase()
        .trim();

      // To ensure backwards compatibility, this is done only to "other" languages.
      if (!Drupal.tableOfContents.mainLanguages().includes(drupalSettings.path.currentLanguage)) {
        Object.keys(Drupal.tableOfContents.localeConversions()).forEach((swap) => {
          name = name.replace(new RegExp(Drupal.tableOfContents.localeConversions()[swap], 'g'), swap);
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
        : Drupal.tableOfContents.findAvailableId(name, 0);

      Drupal.tableOfContents.anchors.push(anchorName);

      // Create anchor links.
      content.setAttribute('id', anchorName);
      content.setAttribute('tabindex', '-1');  // Set tabindex to -1 to avoid issues with screen readers.

      return {
        nodeName: nodeName,
        anchorName: anchorName,
      }
    },

    // A function to reveal table of contents.
    updateTOC: (tocElement) => {
      // Remove loading text and noscript element.
      const removeElements = tocElement.parentElement.querySelectorAll('.js-remove');
      removeElements.forEach(function (element) {
        element.remove();
      });

      // Update toc visible.
      tocElement.setAttribute('data-js', 'true');
    },
  }

  // Attach table of contents.
  Drupal.behaviors.tableOfContents = {
    attach: function attach() {
      const tableOfContents = document.getElementById('helfi-toc-table-of-contents');

      const tableOfContentsList = document.querySelector('#helfi-toc-table-of-contents-list > ul');
      const mainContent = document.querySelector('main.layout-main-wrapper');
      const reservedElems = document.querySelectorAll('[id]');
      reservedElems.forEach(function (elem) {
        Drupal.tableOfContents.reservedIds.push(elem.id);
      });

      if (Drupal.tableOfContents.titleComponents()) {
        // Craft table of contents.
        once('table-of-contents', Drupal.tableOfContents.titleComponents().join(','), mainContent)
          .forEach((content) => {

            const { nodeName, anchorName} = Drupal.tableOfContents.createTableOfContentElements(content, []);

            // Bail if table of contents is not enabled,
            // but retain the heading element id.
            if (!tableOfContents) {
              return;
            }

            // Create table of contents if component is enabled.
            if (tableOfContentsList && nodeName === 'h2') {
              let listItem = document.createElement('li');
              listItem.classList.add('table-of-contents__item');

              let link = document.createElement('a');
              link.classList.add('table-of-contents__link');
              link.href = `#${anchorName}`;
              link.textContent = content.textContent.trim();

              listItem.appendChild(link);
              tableOfContentsList.appendChild(listItem);
            }
          }
        );
      }

      if (tableOfContents) {
        Drupal.tableOfContents.updateTOC(tableOfContents);
      }
    },
  };
})(Drupal, once, drupalSettings);
