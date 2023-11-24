import EuCookieManager from "./EuCookieManager";
import GenesysAdapter from "./adapter/GenesysAdapter";
import SmarttiAdapter from "./adapter/SmarttiAdapter";
import UserInquiryAdapter from "./adapter/UserInquiryAdapter";
import Leijuke from "./Leijuke";

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chat_leijuke = {
    attach: function (context, settings) {

      const leijukeData = drupalSettings.leijuke_data;

      for (const chat_selection in leijukeData) {
        const adapter = getAdapter(chat_selection);
        if (!adapter) return;

        setTimeout(() => {
          // Only load any leijuke once, in case of ajax triggers.
          if (leijukeData[chat_selection].initialized) {
            console.warn(`Already initialized ${chat_selection}!`);
            return;
          }

          new Leijuke(leijukeData[chat_selection], new EuCookieManager, adapter);
          drupalSettings.leijuke_data[chat_selection].initialized = true;
        });
      }
    }
  }

  /**
   * Get concrete implementation required by Drupal.
   */
  function getAdapter(chatSelection) {
    switch(chatSelection) {
      case 'genesys':
        return new GenesysAdapter;
        break;
      case 'smartti':
        return new SmarttiAdapter;
        break;
      case 'user_inquiry':
        return new UserInquiryAdapter;
        break;
      default:
        console.warn(`No adapter found for ${chatSelection}!`);
    }
  }
})(Drupal, drupalSettings);
