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
    };

    this.checkCookies(this.state.cookies);
    this.isChatOpen(this.isOpen);
    this.render();
  }

  checkCookies(cookies) {
    if (cookies) {
      return true
    }

    return false
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
