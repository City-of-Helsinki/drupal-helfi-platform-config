var helfiChatCookiePath = '/fi/sosiaali-ja-terveyspalvelut/';
var helfiChatTransferPath = helfiChatCookiePath + 'genesys-auth-redirect-test?dir=out';
var gcReturnSessionId = '';

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.removeChatIcon = function() {
    $(".cx-window-manager").css("display", "none");
  }

  Drupal.setGcReturnSessionId = function() {
    // helper cookie to maintain chat session id:
    var gcReturnSessionId = Drupal.getCookieChat("_genesys.widgets.webchat.state.session");
    if (!Drupal.isEmpty(gcReturnSessionId) && !Drupal.isBlank(gcReturnSessionId)) {
      // Found GS-chat session, setting it to helper cookie:
      /* document.cookie = "gcReturnSessionId="+gcReturnSessionId+";path=/helsinki/fi/sosiaali-ja-terveyspalvelut/terveyspalvelut/hammashoito/"; */
      document.cookie =
        "gcReturnSessionId=" + gcReturnSessionId + ";path=" + helfiChatCookiePath;
    } else {
      //console.log("gcReturnSessionId", gcReturnSessionId);
      alert(
        "Virhe, ei voida tunnistaa käyttäjää, koska chat-keskustelu ei ole auki."
      );
      return false;
    }
    // save alternative return url for cookie:
    document.cookie =
      "gcAlternativeReturnUrl=" +
      window.location.href +
      ";path=" +
      helfiChatCookiePath;
    return true;
  }

  Drupal.getCookieChat = function(cname) {
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

  Drupal.isEmpty = function(str) {
        return !str || 0 === str.length;
  }

  Drupal.isBlank = function(str) {
    return !str || /^\s*$/.test(str);
  }

  Drupal.behaviors.genesys_suunte = {
    attach: function (context, settings) {
      var helFiChatPageUrl = document.location.href;
      helFiChatPageUrl = helFiChatPageUrl.toLowerCase();
      var helfiChat_lang = document.documentElement.lang;

      var accesabilityTexts = {
        fi: {
          userIconAlt: "käyttäjä",
          agentIconAlt: "agentti",
        },
        en: {
          userIconAlt: "user",
          agentIconAlt: "agent",
        },
        sv: {
          userIconAlt: "användare",
          agentIconAlt: "ombud",
        },
      };

      var startChatButtonClasses = {
        desktop: {
          fi: {
            open: "cx-webchat-chat-button-open",
            busy: "cx-webchat-chat-button-busy",
            close: "cx-webchat-chat-button-closed",
          },
          sv: {
            open: "cx-webchat-chat-button-open-sv",
            busy: "cx-webchat-chat-button-busy-sv",
            close: "cx-webchat-chat-button-closed-sv",
          },
          en: {
            open: "cx-webchat-chat-button-open-en",
            busy: "cx-webchat-chat-button-busy-en",
            close: "cx-webchat-chat-button-closed-en",
          },
        },
        mobile: {
          fi: {
            open: "cx-webchat-chat-button-mobile-open",
            busy: "cx-webchat-chat-button-mobile-busy",
            close: "cx-webchat-chat-button-mobile-closed",
          },
          sv: {
            open: "cx-webchat-chat-button-mobile-open-sv",
            busy: "cx-webchat-chat-button-mobile-busy-sv",
            close: "cx-webchat-chat-button-mobile-closed-sv",
          },
          en: {
            open: "cx-webchat-chat-button-mobile-open",
            busy: "cx-webchat-chat-button-mobile-busy",
            close: "cx-webchat-chat-button-mobile-closed",
          },
        },
      };

      var authEnabled = true;
      var helfiChatLogoElement =
        '<img class="gwc-chat-logo-helsinki" tabindex="0" title="helsinki-logo" alt="helsinki-logo" src="https://www.hel.fi/static/helsinki/chat/project-logo-hki-white-fi.png"/>';

      var mobileIksButton =
        '<div id="gwc-chat-icon-iks-mobile"' +
        'tabindex="0" onkeypress="onEnter(event, this)" role="button" onclick="Drupal.removeChatIcon()"><img src="https://www.hel.fi/static/helsinki/chat/close-next.svg" /><div></div></div';
      var helFiChat_SendButton = '<img class = "hki-cx-send-icon" src="https://www.hel.fi/static/helsinki/chat/arrow_black.svg" />';
      var helFiChat_AgentIcon = '<img class = "hki-cx-avatar-icon" src="https://www.hel.fi/static/helsinki/chat/agent_blue.svg" alt="${accesabilityTexts[helfiChat_lang].agentIconAlt}" />';
      var helFiChat_UserIcon = '<img class="hki-cx-avatar-icon" src="https://www.hel.fi/static/helsinki/chat/user_black.svg" alt="${accesabilityTexts[helfiChat_lang].userIconAlt}" />';

      /* CHAT START BUTTON ICONS */
      var helFiChat_button = "";
      var helFiChat_localization =
        "https://chat-proxy.hel.fi/gms/sote/localization/chat-testisivu-fi.json";
      var helFiChat_service = "TESTISIVU_TESTI"; //SUUNTE
      var helFiChat_language = "fi";
      var helfiChat_GUI_lang = helFiChat_language;
      var helFiChat_title = "Hammashoidon chat";

	    /* FILL BELOW LINE CORRECT PATH WHERE CALLSHIBBOLETH FUNCTION IS RUN OR FUNCTION ITSELF?: */
      var helfiChatAuthElement = '<div id="chatAuthenticationElement"><a href="javascript:void(0);" title="" target="" onclick="var testReturnSessionId=Drupal.setGcReturnSessionId();if(testReturnSessionId){window.location=helfiChatTransferPath;}" href="javascript:void(0);">Tunnistaudu tästä</a></div>';
      var helfiChatAuthElementDone = '<div id="authUserTitleContainer" style="display: none;">Tunnistautunut käyttäjä</div>';


      function callShibboleth() {
        var interactionId = readInteractionIDFromCookie(
          "_genesys.widgets.webchat.state.session"
        );
        var currentPage = window.location;
        var shibbolethString =
          "https://chat-proxy.hel.fi/chat/tunnistus/Shibboleth.sso/KAPALogin?";
        shibbolethString += "target=";
        shibbolethString +=
          "https://chat-proxy.hel.fi/chat/tunnistus/MagicPage/ReturnProcessor";
        /*
              shibbolethString += "%3ForigPage%3D" + "https://www.hel.fi/helsinki/fi/sosiaali-ja-terveyspalvelut/terveyspalvelut/hammashoito/transfer?dir%3Din%26gcLoginButtonState%3D1%26errcode%3d0";
              */
        shibbolethString +=
          "%3ForigPage%3Dhttps://www.hel.fi" +
          helfiChatTransferPath +
          "?dir%3Din%26gcLoginButtonState%3D1%26errcode%3d0";
        shibbolethString += "%26interactionId%3D" + interactionId;
        window.location = shibbolethString;
      }

      function initHelFiChatAuthButtonState() {
        // State of Vetuma authentication result:
        gcReturnSessionId = Drupal.getCookieChat("gcSession");
        // is user authenticated, 1=yes
        var chatGcLoginButtonState = Drupal.getCookieChat("gcLoginButtonState");
        //is genesys original session active now?
        var gcOriginalSessionID = "";
        gcOriginalSessionID = Drupal.getCookieChat("_genesys.widgets.webchat.state.session");
        if (gcOriginalSessionID) {
          setTimeout(function () {
            // if(chatGcLoginButtonState==1) {
            if (
              chatGcLoginButtonState == 1 &&
              document.getElementById("chatAuthenticationElement")
            ) {
              //user is authenticated, show correct link in chat window:
              document.getElementById("chatAuthenticationElement").style.display =
                "none";
              document.getElementById("authUserTitleContainer").style.display = "";
              document.getElementById("authUserTitleContainer").style.display =
                "flex";
            }
            // else {
            else if (document.getElementById("chatAuthenticationElement")) {
              //user is not authenticated, show correct link in chat window:
              document.getElementById("chatAuthenticationElement").style.display = "";
              document.getElementById("chatAuthenticationElement").style.display =
                "flex";
              document.getElementById("authUserTitleContainer").style.display =
                "none";
            }
          }, 2000); /* setTimeout */
        }
      } /* ...end function initHelFiChatAuthButtonState() */

      window.addEventListener("load", initHelFiChatAuthButtonState);

      // ------- Auth functions starts ------------

      // ------- Auth functions ends ------------

      function isMobile() {
        if (screen.width < 600) {
          return true;
        }
        return false;
      }

      (function setChatStartButton() {
        //Check if it's mobile
        var screenType = isMobile() ? "mobile" : "desktop";

        helFiChat_button = "";
        if (helFiChat_button.indexOf("chat-closed") > -1) {
          helFiChat_button = startChatButtonClasses[screenType][helfiChat_lang].close;
        } else if (helFiChat_button.indexOf("chat-busy") > -1) {
          helFiChat_button = startChatButtonClasses[screenType][helfiChat_lang].busy;
        } else {
          helFiChat_button = startChatButtonClasses[screenType][helfiChat_lang].open;
        }
      })();

      function generateStartChatButton() {
        // var screenType = isMobile();
        // // var mobileIksButton = '<div id="gwc-chat-icon-iks-mobile"' +
        // //             'tabindex="0" onkeypress="onEnter(event, this)" role="button" onclick="removeChatIcon()"><img src="https://www.hel.fi/static/helsinki/chat/close-next.svg" /></div>'

        var buttonHtml =
          '<div class="cx-widget cx-webchat-chat-button ' +
          helFiChat_button +
          ' cx-side-button" id="chatButtonStart" role="button" tabindex="0" data-message="ChatButton"' +
          'data-gcb-service-node="true"><span class="cx-icon" data-icon="chat"></span><span class="i18n cx-chat-button-label" data-message="ChatButton"></span></div>';
        return buttonHtml;
      }

      if (!window._genesys) { window._genesys = {};
      }
      if (!window._gt) { window._gt = [];
      }

      window._genesys.widgets = {
        main: {
          theme: "helsinki-blue",
          themes: {
            "helsinki-blue": "cx-theme-helsinki-blue",
          },
          mobileMode: "auto",
          lang: helfiChat_lang,
          i18n: helFiChat_localization,
          mobileModeBreakpoint: 600,
          preload: ["webchat"],
        },
        webchat: {
          dataURL: "https://chat-proxy.hel.fi/gms/sote/genesys/2/chat/prod",
          confirmFormCloseEnabled: false,
          userData: {
            service: helFiChat_service,
          },
          timeFormat: 24,
          cometD: {
            enabled: false,
          },
          autoInvite: {
            enabled: false,
            timeToInviteSeconds: 10,
            inviteTimeoutSeconds: 30,
          },
          chatButton: {
            enabled: true,
            template: generateStartChatButton(),
            effect: "fade",
            openDelay: 1000,
            effectDuration: 300,
            hideDuringInvite: true,
          },
          uploadEnabled: false,
        },
      };

      if (!window._genesys.widgets.extensions) {
        window._genesys.widgets.extensions = {};
      }
      var chatExtension = null;
      chatExtension = CXBus.registerPlugin("ChatExt");

      window._genesys.widgets.extensions["ChatExt"] = function ($, CXBus, Common) {
        chatExtension.before("WebChat.open", function (oData) {
          //Delete X button in mobile view
          //console.log("restarted from open");
          $("#gwc-chat-icon-iks-mobile").css("display", "none");

          if (!oData.restoring) {
            oData = {
              form: {
                autoSubmit: true,
                nickname: "Asiakas",
              },
              formJSON: {
                //wrapper: '<table style="display:none;"></table>',
                inputs: [
                  {
                    id: "cx_webchat_form_nickname",
                    name: "nickname",
                    maxlength: "100",
                    value: "Asiakas",
                    type: "hidden",
                  },
                ],
              },
              userData: {
                service: helFiChat_service,
              },
            };
          }

          //console.log(oData);
          return oData;
        });

        //Triggers when chat is opened
        chatExtension.subscribe("WebChat.opened", function (e) {
          //Add auth layout
          if (authEnabled) {
            $(".cx-body").prepend(helfiChatAuthElement);
            $(".cx-body").prepend(helfiChatAuthElementDone);
          }

          //Add logo
          $(".cx-titlebar .cx-icon").replaceWith(helfiChatLogoElement);

          $(".cx-input-container").removeAttr("tabindex").removeAttr("aria-hidden");
          $(".cx-textarea-cell").removeAttr("tabindex").removeAttr("aria-hidden");
          $(".cx-send").attr("tabindex", 0);
          $(".cx-send").removeAttr("aria-hidden");

          //Delete X button in mobile view
          $("#gwc-chat-icon-iks-mobile").css("display", "none");
          setTimeout(function () {
            $("#gwc-chat-icon-iks-mobile").css("display", "none");
          }, 500);

          //Add accesability enchanced features on minimize
          if ($("[data-icon=minimize]").length) {
            $("[data-icon=minimize]").attr("aria-expanded", true);
          }

          //Change send icon
          if ($(".cx-send").length) {
            $(".cx-send").empty().append(helFiChat_SendButton);
          }

          //Change user icon
          handleChangeAvatarIcons();

          if ($(".cx-message-input.cx-input").length) {
            $(".cx-message-input.cx-input").attr("tabindex", 0);
          }
        });

        function minimizeAccesibilityChange(name) {
          //Minimizdd button accesibility change
          var minimizeElement = $(".cx-button-" + name);
          if (minimizeElement) {
            var ariaExpanded = JSON.parse(minimizeElement.attr("aria-expanded"));
            minimizeElement.attr("aria-expanded", !ariaExpanded);
          }
        }

        //Triggers when chat is ready to accept commands
        chatExtension.subscribe("WebChat.ready", function (e) {
          if (isMobile()) {
            setTimeout(function () {
              //showButton(true);
              if ($(".cx-webchat-chat-button").length) {
                $(".cx-side-button-group").prepend(mobileIksButton);
              }
            }, 3000);
          }
        });

        // Remove custom visibility logic and show button upon closing chat
        chatExtension.subscribe("WebChat.closed", function (e) {
          if (isMobile()) {
            setTimeout(function () {
              if ($(".cx-webchat-chat-button").length) {
                $(".cx-side-button-group").prepend(mobileIksButton);
              }
            }, 3000);
          }
        });

        chatExtension.subscribe("WebChat.cancelled", function (e) {
          // cancelled event. The Chat session ended before agent is connected to WebChat.
          setTimeout(function () {
            chatExtension
              .command("WebChat.close")
              .done(function (e) {
                // closing success
              })
              .fail(function (e) {
                // closing failure
              });
          }, 1000);
        });

        chatExtension.subscribe("WebChat.minimized", function (e) {
          minimizeAccesibilityChange("maximize");
        });

        chatExtension.subscribe("WebChat.unminimized", function (e) {
          minimizeAccesibilityChange("minimize");
        });

        chatExtension.subscribe("WebChat.messageAdded", function (event) {
          handleChangeAvatarIcons();
        });

        function handleChangeAvatarIcons() {
          //Change user icon
          if ($(".cx-avatar.user").length) {
            $(".cx-avatar.user").empty().append(helFiChat_UserIcon);
          }

          //Change agent icons
          if ($(".cx-avatar.agent").length) {
            $(".cx-avatar.agent").empty().append(helFiChat_AgentIcon);
          }
        }

        chatExtension.republish("ready");
        chatExtension.ready();
        window.chatExtension = chatExtension;

      };
    }
  };

})(jQuery, Drupal, drupalSettings);
