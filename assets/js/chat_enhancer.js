((Drupal) => {
  Drupal.behaviors.chat_enhancer = {
    attach: () => {
      // Add the chat_user_consent functionality
      window.chat_user_consent = {
        retrieveUserConsent: () =>
          Drupal.cookieConsent.getConsentStatus(['chat']),
        confirmUserConsent: () => {
          if (Drupal.cookieConsent.getConsentStatus(['chat'])) return;
          Drupal.cookieConsent.setAcceptedCategories(['chat']);
        },
      };

      // Chat accessibility enhancements
      let attemptCount = 0;
      const maxAttempts = 10; // Maximum number of attempts

      // Function to check for the element and set attributes
      function checkAndSetAttributes() {
        const element = document.getElementById('aca-wbc-chat-app-button');

        if (element) {
          // Check and set the 'role' attribute if not set
          if (!element.hasAttribute('role')) {
            element.setAttribute('role', 'region');
          }

          // Check and set the 'aria-labelledby' attribute if not set
          if (!element.hasAttribute('aria-labelledby')) {
            element.setAttribute('aria-labelledby', 'chat-app-button');
          }
        } else if (attemptCount < maxAttempts) {
          // If element is not found, wait and try again after a delay
          attemptCount++;
          setTimeout(checkAndSetAttributes, 1000);
        }
      }

      // Start the initial check
      checkAndSetAttributes();
    },
  };
})(Drupal);
