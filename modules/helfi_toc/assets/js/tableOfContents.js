'use strict';

(function (Drupal, once, drupalSettings) {
  Drupal.behaviors.table_of_contents = {
    attach: function attach() {

      function findAvailableId(name, reserved, anchors, count) {
        let newName = name;
        if (count > 0) { // Only when headings are not unique on page we want to add counter
          newName += `-${count}`;
        }
        if (reserved.includes(newName)) {
          return findAvailableId(name, reserved, anchors, count + 1);
        }

        if (anchors.includes(newName)) {
          if (count === 0) {
            count += 1; // When reserved heading is visible on page, lets start counting from 2 instead of 1
          }
          return findAvailableId(name, reserved, anchors, count + 1);
        }
        return newName;
      }

      const anchors = [];
      const tableOfContents = document.getElementById('helfi-toc-table-of-contents');
      const tableOfContentsNewsUpdates = document.getElementById('helfi-toc-table-of-contents-news-updates');
      const tableOfContentsList = document.querySelector('#helfi-toc-table-of-contents-list > ul');
      const mainContent = document.querySelector('main.layout-main-wrapper');
      const reservedElems = document.querySelectorAll('[id]');
      const reserved = []; // Let's list current id's here to avoid creating duplicates
      reservedElems.forEach(function (elem) {
        reserved.push(elem.id);
      });

      // Exclude elements from TOC that are not content:
      // e.g. TOC, sidebar, cookie compliency-banner etc.
      let exclusions =
        '' +
        ':not(.layout-sidebar-first *)' +
        ':not(.layout-sidebar-second *)' +
        ':not(.tools__container *)' +
        ':not(.breadcrumb__container *)' +
        ':not(#helfi-toc-table-of-contents *)' +
        ':not(.embedded-content-cookie-compliance *)' +
        ':not(.react-and-share-cookie-compliance *)';

      // On a news page we need to concentrate on the news update container.
      if (tableOfContentsNewsUpdates) {
        exclusions +=
          ':not(.components--upper *)' +
          ':not(.block--news-of-interest *)' +
          ':not(#helfi-toc-table-of-contents-news-updates *)';
      }

      const titleComponents = [
        `h2${exclusions}`,
        `h3${exclusions}`,
        `h4${exclusions}`,
        `h5${exclusions}`,
        `h6${exclusions}`,
      ];

      const mainLanguages = [
        'en',
        'fi',
        'sv',
      ];

      const swaps = {
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
        'b': '[бβبဗბｂब]',
        'c': '[çćčĉċｃ©]',
        'd': '[ďðđƌȡɖɗᵭᶁᶑдδدضဍဒდｄᴅᴆ]',
        'e': '[éèẻẽẹêếềểễệëēęěĕėεέἐἑἒἓἔἕὲеёэєəဧေဲეएإئｅ]',
        'f': '[фφفƒფｆ]',
        'g': '[ĝğġģгґγဂგگｇ]',
        'h': '[ĥħηήحهဟှჰｈ]',
        'i': '[íìỉĩịîïīĭįıιίϊΐἰἱἲἳἴἵἶἷὶῐῑῒῖῗіїиဣိီည်ǐიइیｉi̇ϒ]',
        'j': '[ĵјჯجｊ]',
        'k': '[ķĸкκقكကკქکｋ]',
        'l': '[łľĺļŀлλلလლｌल]',
        'm': '[мμمမმｍ]',
        'n': '[ñńňņŉŋνнنနნｎ]',
        'o': '[óòỏõọôốồổỗộơớờởỡợøōőŏοὀὁὂὃὄὅὸόоوθိုǒǿºოओｏöө]',
        'p': '[пπပპپｐ]',
        'q': '[ყｑ]',
        'r': '[ŕřŗрρرრｒ]',
        's': '[śšşсσșςسصစſსｓŝ]',
        't': '[ťţтτțتطဋတŧთტｔ]',
        'u': '[úùủũụưứừửữựûūůűŭųµуဉုူǔǖǘǚǜუउｕўü]',
        'v': '[вვϐｖ]',
        'w': '[ŵωώဝွｗ]',
        'x': '[χξｘ]',
        'y': '[ýỳỷỹỵÿŷйыυϋύΰيယｙῠῡὺ]',
        'z': '[źžżзζزဇზｚ]',
        'aa': '[عआآ]',
        'ae': '[æǽ]',
        'ai': '[ऐ]',
        'ch': '[чჩჭچ]',
        'dj': '[ђđ]',
        'dz': '[џძ]',
        'ei': '[ऍ]',
        'gh': '[غღ]',
        'ii': '[ई]',
        'ij': '[ĳ]',
        'kh': '[хخხ]',
        'lj': '[љ]',
        'nj': '[њ]',
        'oe': '[öœؤ]',
        'oi': '[ऑ]',
        'oii': '[ऒ]',
        'ps': '[ψ]',
        'sh': '[шშش]',
        'shch': '[щ]',
        'ss': '[ß]',
        'sx': '[ŝ]',
        'th': '[þϑثذظ]',
        'ts': '[цცწ]',
        'ue': '[ü]',
        'uu': '[ऊ]',
        'ya': '[я]',
        'yu': '[ю]',
        'zh': '[жჟژ]',
        'gx': '[ĝ]',
        'hx': '[ĥ]',
        'jx': '[ĵ]',
      };

      // Craft table of contents.
      once('table-of-contents', titleComponents.join(','), mainContent)
        .forEach(function (content) {
          let name = content.textContent
            .toLowerCase()
            .trim();

          // To ensure backwards compatibility, this is done only to "other" languages.
          if (!mainLanguages.includes(drupalSettings.path.currentLanguage)) {
            Object.keys(swaps).forEach((swap) => {
              name = name.replace(new RegExp(swaps[swap], 'g'), swap);
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
            : findAvailableId(name, reserved, anchors, 0);

          anchors.push(anchorName);

          // On updating news there is published date under the title that we want to display in the
          // table of contents news update version. For normal table of contents this remains empty.
          let contentPublishDate = '';

          if (tableOfContentsNewsUpdates && content.nextSibling && content.nextElementSibling.nodeName === 'TIME') {
            let contentPublishDateStamp = new Date(content.nextElementSibling.dateTime);
            contentPublishDate = `${contentPublishDateStamp.getDate()}.${contentPublishDateStamp.getMonth() + 1}.${contentPublishDateStamp.getFullYear()}`;
          }

          // Create table of contents if component is enabled.
          if (tableOfContentsList && nodeName === 'h2') {
            let listItem = document.createElement('li');
            listItem.classList.add('table-of-contents__item');

            let link = document.createElement('a');
            link.classList.add('table-of-contents__link');
            link.href = `#${anchorName}`;
            link.textContent = content.textContent.trim();

            // Add content publish date and its wrapper to list items only if they exist.
            if (contentPublishDate) {
              let publishDate = document.createElement('time');
              publishDate.dateTime = content.nextElementSibling.dateTime;
              publishDate.textContent = contentPublishDate;

              listItem.appendChild(publishDate);
            }

            listItem.appendChild(link);
            tableOfContentsList.appendChild(listItem);
          }
          // Create anchor links.
          content.setAttribute('id', anchorName);
          content.setAttribute('tabindex', '-1');  // Set tabindex to -1 to avoid issues with screen readers.
        });

      function updateTOC(tocElement) {
        // Remove loading text and noscript element.
        const removeElements = tocElement.parentElement.querySelectorAll('.js-remove');
        removeElements.forEach(function (element) {
          element.remove();
        });

        // Update toc visible.
        tocElement.setAttribute('data-js', 'true');
      }

      // Select which type of TOC should be displayed.
      if (tableOfContentsNewsUpdates) {
        updateTOC(tableOfContentsNewsUpdates);
      } else {
        updateTOC(tableOfContents);
      }

    },
  };
})(Drupal, once, drupalSettings);
