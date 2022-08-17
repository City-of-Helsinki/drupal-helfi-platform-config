(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {

      let cookieCheck = (cookieNames) => {
        let cookiesOk = true;
        cookieNames.map((cookieName) => {
          if (!Drupal.eu_cookie_compliance.hasAgreedWithCategory(cookieName)) cookiesOk = false;
        });
        console.log(`${cookiesOk ? 'OK: ': 'NO: '} Checked cookies: `, cookieNames);
        return cookiesOk;
      };

      let cookieSet = (cookieNames) => {
        Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), ...cookieNames ]);
        console.log('Checked cookies: ', cookieNames);
      };

      const leijukeState = drupalSettings.leijuke_state;
      setTimeout(() => {
        new Leijuke(leijukeState, cookieCheck, cookieSet);
      });

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
  constructor(leijukeState, cookieCheck, cookieSet) {
    // required cookies kovakoodattu
    this.requiredCookies = {
      genesys_chat: ['chat'],
      genesys_suunte: ['chat'],
      genesys_neuvonta: ['chat'],
      kuura_health_chat: ['chat', 'statistics'],
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

    this.state = {
      cookies: cookieCheck(this.requiredCookies[leijukeState.chat_selection]),
      chatSelection: leijukeState.chat_selection,
      chatLoaded: false,
      isOpen: this.isChatOpen(),
      modulePath: leijukeState.modulepath,
      libraries: leijukeState.libraries
    };

    // this.loadChat();
    // this.checkCookies(this.state.cookies);
    // this.isChatOpen(this.isOpen);
    if (this.state.cookies) {
      this.loadChat();
      this.state = {
        ...this.state,
        chatLoaded: true
      };
    }
    console.log('current state', this.state);
    this.render();

    const button = document.querySelector('#chat-leijuke');

    button.addEventListener('click', (event) => {
      if (this.state.chatLoaded) {
        console.log('Chat was loaded previously, just opening it now.');

        this.openChat();
        return;
      }

      if (!this.state.cookies) {
        // Implicitly allow chat cookies if clicking Leijuke.
        console.log('Chat cookies allowed implicitly and chat being loaded.');

        cookieSet(this.requiredCookies[this.state.chatSelection]);
        this.loadChat();
        this.state = {
          ...this.state,
          cookies: cookieCheck(this.requiredCookies[this.state.chatSelection]),
          chatLoaded: true
        };
      }
      this.openChat();

      console.log('Chat should be opened and rerender leijuke.');
    });
  }

  persist(cookiename, value) {
    if (value === undefined) {
      let cookie = this.getCookie(cookiename);
      console.log({cookie});
      return cookie != false;
    }
    this.setCookie(cookiename, value);
  }

  setCookie(cname, cvalue) {
    document.cookie = cname + "=" + cvalue + ";path=/";
  }

  getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(";");
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) == " ") {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
  }

  openChat() {
    // do what now?
    this.persist('leijuke.isOpen', true);
    this.state = {
      ...this.state,
      isOpen: true,
    };
    this.render();
  }

  loadChat() {
    const { modulePath, libraries } = this.state;

    libraries.js.map((script) => {
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

    libraries.css.map((script) => {
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
  isChatOpen() {
    return this.persist('leijuke.isOpen');
  }

  render() {
    const { isOpen } = this.state;

    document
    .getElementById("block-chatleijuke")
    .innerHTML = `
      <div id="chat-leijuke" ${isOpen ? 'class="open"' : ''}>
        <span class="hel-icon hel-icon--speechbubble-text"></span><span>${this.leijukeTitle[this.state.chatSelection]}</span><span class="hel-icon hel-icon--angle-up"></span>
      </div>
    `;

  }
}
