(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {

      const leijukeState = drupalSettings.leijuke_state;
      new Leijuke(leijukeState);

      // tsekataan cookie(t)
      // ladataan oikea chat/chatbot
      // tsekataan onko chat auki vai ei - tietääkö chat onko auki vai tarvitaanko oma cookie?
      // piirretään leijuke, jos ei cookieita tai jos chat ei auki
      // jos clikataan leijukkeesta niin implisiittisesti asetetaan chat cookiet
      // Drupal.removeChatIcon() tms
    }
  }
})(jQuery, Drupal, drupalSettings);


class Leijuke {
  constructor(leijukeState) {
    this.state = {
      cookies: [],
      chatSelection: '',
      isOpen: false,
      modulePath: leijukeState.modulepath
    };

    this.loadChat();
    // this.checkCookies(this.state.cookies);
    // this.isChatOpen(this.isOpen);
    this.render();

  }

  checkCookies(cookies) {
    if (cookies) {
      return true
    }

    return false
  }

  loadChat() {
    const { modulePath } = this.state;
    // modulePath genesys chat kovakoodattu
    const library = {
      js: [
        {
          url: 'https://apps.mypurecloud.ie/widgets/9.0/cxbus.min.js',
          ext: true,
          onload: "javascript:CXBus.configure({pluginsPath:'https://apps.mypurecloud.ie/widgets/9.0/plugins/'}); CXBus.loadPlugin('widgets-core');"
        },
        {
          url: 'assets/js/genesys_chat.js'
        }
      ],
      css: [
        {
          url: "assets/css/genesys_chat.css"
        }
      ]
    }

    library.js.map((script) => {
      // Create a new element
      let chatScript = document.createElement('script');
      chatScript.src = script.ext ? script.url : `/${modulePath}/${script.url}`;
      chatScript.type = "text/javascript";

      if (script.onload) {
        chatScript.setAttribute('onload', script.onload);
      }

      // Get the parent node
      let head = document.querySelector('head');
      // Insert chatScript into head
      head.appendChild(chatScript);

    })

    library.css.map((script) => {
      // Create new link Element for loading css
      let css= document.createElement('link');
      css.rel = 'stylesheet';
      css.href = script.ext ? script.url : `/${modulePath}/${script.url}`

      // Get the parent node
      let head = document.querySelector('head');

      // Insert chatScript into head
      head.append(css);
    })
  }

  // funktio joka tsekkaa onko chat auki vai ei - tietääkö chat onko auki vai tarvitaanko oma cookie?
  isChatOpen(isOpen) {

  }

  render() {
    const { isOpen } = this.state;

    document
      .getElementById("block-chatleijuke")
      .innerHTML = `
        <div>
          Leijuke
        </div>
     `;
  }
}
