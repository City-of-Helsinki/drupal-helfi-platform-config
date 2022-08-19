(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {

      const cookieCheck = (cookieNames) => {
        let cookiesOk = true;
        cookieNames.map((cookieName) => {
          if (!Drupal.eu_cookie_compliance.hasAgreedWithCategory(cookieName)) cookiesOk = false;
        });
        console.log(`${cookiesOk ? 'OK: ': 'NO: '} Checked cookies: `, cookieNames);
        return cookiesOk;
      };

      const cookieSet = (cookieNames) => {
        Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), ...cookieNames ]);
        console.log('Checked cookies: ', cookieNames);
      };

      const leijukeData = drupalSettings.leijuke_data;
      setTimeout(() => {
        new Leijuke(leijukeData, cookieCheck, cookieSet);
      });
    }
  }
})(Drupal, drupalSettings);


class Leijuke {
  constructor(leijukeData, cookieCheck, cookieSet) {
    this.static = {
      chatSelection: leijukeData.chat_selection,
      cookieName: `leijuke.${leijukeData.chat_selection}.isOpen`,
      modulePath: leijukeData.modulepath,
      libraries: leijukeData.libraries,
      leijukeTitle: leijukeData.leijuke_title,
      requiredCookies: leijukeData.required_cookies
    }

    this.state = {
      cookies: cookieCheck(this.static.requiredCookies),
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
    console.log('current static', this.static);

    this.initWrapper();
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

        cookieSet(this.static.requiredCookies);
        this.loadChat();
        this.state = {
          ...this.state,
          cookies: cookieCheck(this.static.requiredCookies),
          chatLoaded: true
        };
      }
      this.openChat();

      console.log('Chat should be opened and rerender leijuke.');
    });
  }

  setLeijukeCookie(cname, cvalue) {
    document.cookie = `${cname}=${cvalue}; path=/; SameSite=Strict; `;
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

      // Insert chatScript into head
      let head = document.querySelector('head');
      head.appendChild(chatScript);
    })

    libraries.css.map((script) => {
      // Create new link Element for loading css
      let css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = script.ext ? script.url : `/${modulePath}/${script.url}`;

      // Insert chatScript into head
      let head = document.querySelector('head');
      head.append(css);
    })
  }

  isChatOpen() {
    return this.getLeijukeCookie(this.static.cookieName) === '' ? false : true;
  }

  initWrapper() {
    let leijukeWrapper = document.createElement('div');
    leijukeWrapper.id = 'chat-leijuke-wrapper';
    document.body.append(leijukeWrapper)
  }

  render() {
    const { isChatOpen } = this.state;

    document
    .getElementById("chat-leijuke-wrapper")
    .innerHTML = `
      <div id="chat-leijuke" ${isChatOpen ? 'class="hidden"' : ''}>
        <span class="hel-icon hel-icon--speechbubble-text"></span><span>${this.static.leijukeTitle}</span><span class="hel-icon hel-icon--angle-up"></span>
      </div>
    `;

  }
}
