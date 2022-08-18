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

      const leijukeData = drupalSettings.leijuke_data;
      setTimeout(() => {
        new Leijuke(leijukeData, cookieCheck, cookieSet);
      });
    }
  }
})(jQuery, Drupal, drupalSettings);


class Leijuke {
  constructor(leijukeData, cookieCheck, cookieSet) {
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

    this.static = {
      chatSelection: leijukeData.chat_selection,
      cookieName: `leijuke.${leijukeData.chat_selection}.isOpen`,
      modulePath: leijukeData.modulepath,
      libraries: leijukeData.libraries
    }

    this.state = {
      cookies: cookieCheck(this.requiredCookies[leijukeData.chat_selection]),
      chatLoaded: false,
      isChatOpen: this.isChatOpen(),
      isHidden: false,
    };

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

        cookieSet(this.requiredCookies[this.static.chatSelection]);
        this.loadChat();
        this.state = {
          ...this.state,
          cookies: cookieCheck(this.requiredCookies[this.static.chatSelection]),
          chatLoaded: true
        };
      }
      this.openChat();

      console.log('Chat should be opened and rerender leijuke.');
    });
  }

  setLeijukeCookie(cname, cvalue) {
    document.cookie = cname + "=" + cvalue + ";path=/";
  }

  getLeijukeCookie(cname) {
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
    this.setLeijukeCookie(this.static.cookieName, true);
    this.state = {
      ...this.state,
      isChatOpen: true,
    };
    this.render();
  }

  loadChat() {
    const { modulePath, libraries } = this.static;
    libraries.js.map((script) => {
      // Create a new element
      let chatScript = document.createElement('script');
      chatScript.src = script.ext ? script.url : `/${modulePath}/${script.url}`;
      chatScript.type = "text/javascript";

      if (script.onload) {
        chatScript.setAttribute('onload', script.onload);
      }

      if (script.async) {
        chatScript.setAttribute('async', '');
      }

      if (script.dataContainerId) {
        chatScript.setAttribute('data-container-id', script.data_container_id);
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
      css.href = script.ext ? script.url : `/${modulePath}/${script.url}`;

      // Get the parent node
      let head = document.querySelector('head');

      // Insert chatScript into head
      head.append(css);
    })
  }

  // funktio joka tsekkaa onko chat auki vai ei - tietääkö chat onko auki vai tarvitaanko oma cookie?
  isChatOpen() {
    return this.getLeijukeCookie(this.static.cookieName);
  }

  render() {
    const { isChatOpen } = this.state;

    document
    .getElementById("block-chatleijuke")
    .innerHTML = `
      <div id="chat-leijuke" ${isChatOpen ? 'class="hidden"' : ''}>
        <span class="hel-icon hel-icon--speechbubble-text"></span><span>${this.leijukeTitle[this.static.chatSelection]}</span><span class="hel-icon hel-icon--angle-up"></span>
      </div>
    `;

  }
}
