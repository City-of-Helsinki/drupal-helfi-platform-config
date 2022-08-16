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
    // required cookies kovakoodattu
    this.requiredCookies = {
      genesys_chat: ['chat'],
      genesys_suunte: ['chat'],
      genesys_neuvonta: ['chat'],
      kuura_health_chat: ['chat', 'tilasto_chat'],
      watson_chatbot: ['chat'],
      smartti_chatbot: ['chat'],
    };

    // leijuke title kovakoodattu
    this.leijukeTitle = {
      genesys_chat: 'Chat',
      genesys_suunte: 'Chat',
      genesys_neuvonta: 'Chat',
      kuura_health_chat: 'Chat',
      watson_chatbot: 'Chatbot',
      smartti_chatbot: 'Chatbot',
    }

    // genesys chat kovakoodattu
    this.library = {
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

    this.state = {
      cookies: [
        { name: 'tilasto_chat', value: false },
        { name: 'chat', value: true }
      ],
      chatSelection: leijukeState.chat_selection,
      isOpen: false,
      modulePath: leijukeState.modulepath,
      chatLoaded: false,
    };

    // this.loadChat();
    // this.checkCookies(this.state.cookies);
    // this.isChatOpen(this.isOpen);
    this.render();

    const button = document.querySelector('#chat-leijuke');

    button.addEventListener('click', (event) => {
      if (this.state.chatLoaded) {
        this.openChat();
      }
      if (this.checkCookies(this.state.cookies)) {
        this.loadChat();
      }
    });
  }

  openChat() {
    // do what now?
  }

  checkCookies(cookies) {
    let cookiesOk = true;
    cookies.map((cookie) => {
      if (!cookie.value && this.requiredCookies[this.state.chatSelection].indexOf(cookie.name) !== -1) {
        cookiesOk = false;
      }
    }, cookiesOk);

    console.log({cookiesOk});
    return cookiesOk;
  }

  loadChat() {
    const { modulePath } = this.state;

    this.library.js.map((script) => {
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

    this.library.css.map((script) => {
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
        <div id="chat-leijuke">
          <span class="hel-icon hel-icon--speechbubble-text"></span><span>${this.leijukeTitle[this.state.chatSelection]}</span><span class="hel-icon hel-icon--angle-up"></span>
        </div>
     `;
  }
}
