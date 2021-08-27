(function ($) {
    $(document).ready(
        function () {
            /* GLOBAL variables for controlling old and new chats: */
            var gcReturnSessionId = '';
            var chatGcLoginButtonState = '';
            var helFiChat_variant = '';
            var helFiChatPageUrl = document.location.href;
            helFiChatPageUrl = helFiChatPageUrl.toLowerCase();
            var helFiChatPageUrlPathname = document.location.pathname;
            //new:
            var helFiChat_service_availability_info = '';
            var helFiChat_serverUrl = '';
            var helFiChat_service = '';
            var helFiChat_language = 'FI';
            var helFiChat_logo = '';
            var helFiChat_button = '';
            var helFiChat_closed_button = '';
            var helFiChat_open_button = '';
            var helFiChat_busy_button = '';
            var helFiChat_closed_button_mobile = '';
            var helFiChat_open_button_mobile = '';
            var helFiChat_busy_button_mobile = '';

            var helFiChat_title_temp = '';
            var helFiChat_title = document.title;
            var helFiChat_emailAlias = '';
            var helFiChat_src = '';
            var helFiChat_cbUrl = '';
            var helFiChat_localization = '';

            /* detect if other chat instances are already running: */
            if (typeof helfiChat_GUI_lang === "undefined") {
                var helfiChat_GUI_lang = 'FI';
                console.log('No other chats running, continue loading this chat');
            } else {
                var helfiChat_GUI_lang = ''; /* other chats running, do not start this chat */
                helFiChat_localization = 'ALREADY_DONE';
                console.log('Other chats running, do not start this chat');
            }

            var helfiChat_lang = document.documentElement.lang;
            var helfiChat_user = '';
            var helfiChat_agent = '';
            var arrowDownAriaLabel = '';
            var arrowUpAriaLabel = '';
            var arrowDownTitle = '';
            var arrowUpTitle = '';
            var arrowDownAlt = '';
            var arrowUpAlt = '';
            var helfiChat_close = '';
            var helfiChat_minimize = '';
            var helfiChat_send = '';
            var helfiChat_placeholder = '';
            var helfiChat_message = '';
            var helfiChatLogoElement = '';
            var helfiChatButtonCloseElement = '';
            var helfiChatButtonSendElement = '';
            var helfiChatButtonMinimizeElement = '';
            var helfiChatAgentElement = '';
            var helfiChatAuthElement = '';
            var helfiChatAuthElementDone = '';
            var helfiChatCustomCss = '';
            var helfiChat_close_mobile = '';

            /* for using multiple chat instances, cookiepath is needed */
            var helfiChatCookiePath = '';

            /* NEW CHAT GUI, COMMON VARIABLES FOR ALL CHATS: */
            helFiChat_closed_button = 'background-image: url(\"https://www.hel.fi/static/helsinki/chat/chat-closed.png\") !important';
            /* changed to off-line button: */
            helFiChat_open_button = 'background-image: url(\"https://www.hel.fi/static/helsinki/chat/chat-open.png\") !important';
            /* offline-button: */
            helFiChat_open_button = 'background-image: url(\"https://www.hel.fi/static/helsinki/chat/chat-open.png\") !important';
            helFiChat_busy_button = 'background-image: url(\"https://www.hel.fi/static/helsinki/chat/chat-busy.png\") !important';
            helFiChat_closed_button_mobile = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/mobile_closed_strike1.svg" ) !important';
            helFiChat_open_button_mobile = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/mobile_open1.svg" ) !important';
            helFiChat_busy_button_mobile = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/mobile_busy1.svg" ) !important';


            /* Detect if chat's are already running:*/
            if (helFiChat_localization == '') {

                /* ------------------ TEST CHAT INSTANCE --------------------- */
                helFiChat_src = 'https://asiointi.hel.fi/chat/sote/cobrowse/js/gcb.min.js';
                helFiChat_cbUrl = 'https://asiointi.hel.fi/chat/sote/cobrowse';
                helFiChat_serverUrl = 'https://asiointi.hel.fi/chat/sote/cobrowse';
                helFiChat_localization = 'https://asiointi.hel.fi/chat/sote/custom/chat-testisivu-fi.json';
                helFiChat_service = 'TESTISIVU_TESTI';
                helFiChat_language = 'FI';
                helfiChat_GUI_lang = helFiChat_language;
                helFiChat_title_temp = 'TEST CHAT';
                helFiChat_variant = 'new';
                helfiChatCookiePath = '';


                /* ------------------ PRODUCTION CHAT: VIRKAINFO FI --------------------- */
                /* helFiChat_src = 'https://asiointi.hel.fi/chat/kanslia/cobrowse/js/gcb.min.js';
                helFiChat_cbUrl = 'https://asiointi.hel.fi/chat/kanslia/cobrowse';
                helFiChat_localization = 'https://asiointi.hel.fi/chat/kanslia/custom/chat-virkainfo-fi.json';
                helFiChat_serverUrl = 'https://asiointi.hel.fi/chat/kanslia/cobrowse';
                helFiChat_service = 'VIRKAINFO';
                helFiChat_language = 'FI';
                helfiChat_GUI_lang = helFiChat_language;
                helFiChat_title_temp = 'Helsinki-infon neuvonta';
                helFiChat_variant = 'new';
                helfiChatCookiePath  = '';
                */

                /* === Populate generic language elements for all chats === */
                if (helfiChat_GUI_lang == "SV") {
                    helfiChat_close = 'title="sluten" alt="sluten"';
                    helfiChat_minimize = 'title="minimera" alt=""';
                    helfiChat_send = 'title="send" alt="send"';
                    helfiChat_placeholder = 'Skriv ett meddelande...';
                    helfiChat_message = 'Nytt meddelande';
                    helfiChat_user = 'alt="användare"';
                    helfiChat_agent = 'alt="ombud"';
                    // arrowDownAriaLabel = 'Minimera chatten';
                    arrowDownAriaLabel = 'Chatten';
                    arrowUpAriaLabel = 'Återgå chatt till vanlig storlek';
                    arrowDownTitle = 'minimera';
                    arrowUpTitle = 'maximera';
                    arrowDownAlt = 'minimera';
                    arrowUpAlt = 'maximera';
                    helfiChat_close_mobile = 'aria-label="Ta bort chattikonen"';
                } else if (helfiChat_GUI_lang == "EN") {
                    helfiChat_close = 'title="close" alt="close"';
                    helfiChat_minimize = 'title="minimize" alt=""';
                    helfiChat_send = 'title="send" alt="send"';
                    helfiChat_placeholder = 'Write a message...';
                    helfiChat_message = 'New message';
                    helfiChat_user = 'alt="user"';
                    helfiChat_agent = 'alt="agent"';
                    // arrowDownAriaLabel = 'Minimize chat';
                    arrowDownAriaLabel = 'Chat';
                    arrowUpAriaLabel = 'Return chat to regular size';
                    arrowDownTitle = 'minimize';
                    arrowUpTitle = 'maximize';
                    arrowDownAlt = 'minimize';
                    arrowUpAlt = 'maximize';
                    helfiChat_close_mobile = 'aria-label="Remove chat icon"';
                } else {
                    /* fi chat or fallback: */
                    helfiChat_close = 'title="sulje" alt="sulje"';
                    helfiChat_minimize = 'title="pienennä" alt=""';
                    helfiChat_send = 'title="lähetä viesti" alt="lähetä viesti"';
                    helfiChat_placeholder = 'Kirjoita viesti...';
                    helfiChat_message = 'Uusi viesti';
                    helfiChat_user = 'alt="käyttäjä"';
                    helfiChat_agent = 'alt="agentti"';
                    // arrowDownAriaLabel = 'Pienennä chat';
                    arrowDownAriaLabel = 'Chat';
                    arrowUpAriaLabel = 'Palauta chat normaalikokoiseksi';
                    arrowDownTitle = 'pienennä';
                    arrowUpTitle = 'suurenna';
                    arrowDownAlt = 'pienennä';
                    arrowUpAlt = 'suurenna';
                    helfiChat_close_mobile = 'aria-label="Poista chat kuvake"';
                }

                helfiChatLogoElement = '<img class="gwc-chat-logo-helsinki" tabindex="0" title="helsinki-logo" alt="helsinki-logo" src="https://www.hel.fi/static/helsinki/chat/project-logo-hki-white-fi.png"/>';
                /* WEBSITE VERSION:
                helfiChatButtonCloseElement  = '<img class="gwc-chat-icon-iks" onkeypress="onEnter(event, this)" tabindex="0" role="button"' + helfiChat_close + 'src="https://www.hel.fi/static/helsinki/chat/close-next.svg" />';
                helfiChatButtonCloseElementFromMobileView  = '<div class="gwc-chat-icon-iks-mobile" tabindex="0" role="button" onkeypress="onEnter(event, this)" onclick="removeChatIcon()"><img ' + helfiChat_close_mobile + 'src="https://www.hel.fi/static/helsinki/chat/close-next.svg" /><div>';
                */
                /* OFFLINE VERSION: */
                helfiChatButtonCloseElement = '<img class="gwc-chat-icon-iks" onkeypress="onEnter(event, this)" tabindex="0" role="button"' + helfiChat_close + 'src="close-next.svg" style="width: 100%\;width:35px\;pointer:cursor\;"/>';
                helfiChatButtonCloseElementFromMobileView = '<div class="gwc-chat-icon-iks-mobile" tabindex="0" role="button" onkeypress="onEnter(event, this)" onclick="removeChatIcon()"><img ' + helfiChat_close_mobile + 'src="close-next.svg" style="width: 100%\;width:35px\;pointer:cursor\;"/><div>';

                helfiChatButtonMinimizeElement = '<img id="minimize" onclick="toggleFlip()" onkeypress="onEnter(event, this)" class="gwc-chat-icon-down"  tabindex="0" role="button"' + helfiChat_minimize + 'src="https://www.hel.fi/static/helsinki/chat/arrow_down.svg" />';
                helfiChatButtonSendElement = '<input class="gwc-chat-btn" onkeypress="onEnter(event, this)"  tabindex="0" type="image"' + helfiChat_send + 'src="https://www.hel.fi/static/helsinki/chat/arrow_black.svg" />';
                helfiChatAgentElement = '<img class="gwc-chat-head-icon" style="width:35px !important; height:40px !important;" src="https://www.hel.fi/static/helsinki/chat/agent_blue.svg"' + helfiChat_agent + '/>';

                // for new chats, backend  chat availability status (open-busy-closed), shared for all chat languages:
                helFiChat_button = helFiChat_open_button; /* here hard-coded value, since no backend connectivity */

            } /* secondary url detection completed */

            /* disable or enable helper functions for chat generic close button after asking first if ok to close chat window: */
            function disableHelFiChatCloseButton()
            {
                if (document.getElementsByClassName('gwc-chat-control-close').length > 0) {
                    document.getElementsByClassName('gwc-chat-control-close')[0].className = 'gwc-chat-control-close_DISABLED';
                }
            }

            function enableHelFiChatCloseButton()
            {
                if (document.getElementsByClassName('gwc-chat-control-close_DISABLED').length > 0) {
                    document.getElementsByClassName('gwc-chat-control-close_DISABLED')[0].className = 'gwc-chat-control-close';
                }
            }

            if (helFiChat_localization && helFiChat_variant == 'new') {
                (function (d, s, id, o) {
                    var fs = d.getElementsByTagName(s)[0],
                    e;
                    if (d.getElementById(id)) { return;
                    }
                    e = d.createElement(s);
                    e.id = id;
                    e.src = o.src;
                    e.setAttribute('data-gcb-url', o.cbUrl);
                    fs.parentNode.insertBefore(e, fs);
                })(
                    document, 'script', 'genesys-js', {
                        src: helFiChat_src,
                        cbUrl: helFiChat_cbUrl
                    }
                );
            } //helFiChat_localization

            if (helFiChat_localization && helFiChat_variant == 'new') {
                var _genesys = {
                    onReady: [],
                    chat: {
                        registration: false,
                        localization: helFiChat_localization,
                        onReady: [],
                        ui: {
                            onBeforeChat: function (chat) {
                                setTimeout(
                                    function () {
                                        windowWidth = $(window).width();
                                        windowHeight = $(window).height();
                                        bodyHeight = windowHeight - 50;
                                        if (windowWidth <= 575) {
                                            $('.gwc-chat-body').height(bodyHeight);
                                        } else if (windowHeight <= 425) {
                                            $('.gwc-chat-body').height(bodyHeight);
                                        }

                                        var headElement = '.gwc-chat-head';
                                        var text = helFiChat_title_temp;

                                        jQuery(headElement).find('.gwc-chat-title').replaceWith('<h2 class="gwc-chat-title">' + text + '</h2>');
                                        $('.gwc-chat-head').prepend($('.gwc-chat-logo'));
                                        $('.gwc-chat-logo').replaceWith(helfiChatLogoElement);
                                        $('.gwc-chat-branding').remove();

                                        $('.gwc-chat-body').prepend(helfiChatAuthElement);
                                        $('.gwc-chat-body').prepend(helfiChatAuthElementDone);

                                        if ($('#chatAuthenticationElement').css('display') == 'flex') {
                                            $('div.gwc-chat-message-container').css("top", "100px");
                                        } else {
                                            $('div.gwc-chat-message-container').css('top', '63px');
                                        }
                                        $('div.gwc-chat-message-container').css('top', '63px');
                                        $('.gwc-chat-icon-close').replaceWith(helfiChatButtonCloseElement);
                                        $('.gwc-chat-icon-minimize').replaceWith(helfiChatButtonMinimizeElement);
                                        $('.gwc-chat-message-form').append(helfiChatButtonSendElement);

                                        $("textarea").attr(
                                            {
                                                rows: "1",
                                                placeholder: helfiChat_placeholder
                                            }
                                        );
                                        $("textarea").attr("tabindex", "0");
                                        $("textarea").attr("onfocusout", "addFocus()");
                                        $("textarea").attr("onclick", "removeFocus()");
                                        $("textarea").attr("aria-label", helfiChat_message);
                                        element = document.getElementsByClassName("gwc-chat-embedded-window")[0];
                                        element.setAttribute('role', 'region');
                                        element.setAttribute('aria-roledescription', text);
                                        document.getElementsByClassName('gcb-startChat')[0].setAttribute('aria-expanded', 'true');
                                        document.getElementsByClassName("gwc-persistent-chat-messages")[0].setAttribute('aria-live', 'polite');
                                    }, 0
                                );

                                //When chat is minimized and changing page this code helps to change minimize arrow parameters
                                setTimeout(
                                    function () {
                                        if ($(".gwc-chat-body").css('display') == 'none') {
                                            $("#minimize").addClass("flip");
                                        }
                                        var win = $(this);
                                        /* WEB SITE VERSION:
                                        if(!$(".gwc-chat-body").length && (win.width() <= 575 || win.height() <= 425)) {
                                        */
                                        /* OFFLINE-VERSION: */
                                        if (!$(".gwc-chat-body").length && (window.innerWidth <= 575 || window.innerHeight <= 425)) {
                                            if (document.getElementsByClassName("gwc-chat-icon-iks-mobile").length !== 0) {
                                                console.log("add X BUTTON");
                                                $(".gcb-startBtnsContainer").prepend(helfiChatButtonCloseElementFromMobileView);
                                            }
                                        } else {
                                            if (document.getElementsByClassName("gwc-chat-icon-iks-mobile").length !== 0) {
                                                document.getElementsByClassName("gwc-chat-icon-iks-mobile")[0].style.display = 'none';
                                            }
                                        }
                                    }, 1
                                )

                                _genesys.chat.onReady.push(
                                    function (chatWidgetApi) {
                                        chatWidgetApi.restoreChat(
                                            {
                                                serverUrl: helFiChat_serverUrl,
                                                registration: function (done) {
                                                    done(
                                                        {
                                                            service: helFiChat_service,
                                                            Language: helFiChat_language,
                                                        }
                                                    );
                                                }
                                            }
                                        ).done(
                                            function (session) {
                                                session.setUserData(
                                                    {
                                                        service: helFiChat_service,
                                                        Language: helFiChat_language,
                                                    }
                                                );
                                                setTimeout(
                                                    function () {
                                                            $('.gwc-chat-btn').on(
                                                                "click", function () {
                                                                    var text = $('textarea.gwc-chat-input').val();
                                                                    session.sendMessage(text);
                                                                    $('textarea.gwc-chat-input').val('').focus();
                                                                }
                                                            );
                                                    }, 0
                                                )
                                                session.onMessageReceived(
                                                    function (event) {
                                                        if (document.getElementsByClassName('gwc-chat-message-time').length !== 0) {
                                                            document.getElementsByClassName('gwc-chat-message-time')[0].setAttribute('aria-atomic', 'true');
                                                        }
                                                        $(".gwc-chat-message-time").last().html(
                                                            function () {
                                                                var time = $(this).html().slice(1, -4);
                                                                $(this).html(time);
                                                            }
                                                        )
                                                        if (event.party.type.client) {
                                                            $(".gwc-chat-message-author").last().siblings(".gwc-chat-message-text").attr('id', 'user-message').removeClass("gwc-chat-message-text");
                                                            //helfiChatAuthorUser();
                                                            $("div#user-message").prev().css(
                                                                {
                                                                    "text-align": "right",
                                                                    "display": "block",
                                                                    "color": "grey",
                                                                    "font-size": "12px"
                                                                        }
                                                            );
                                                            $(".gwc-chat-message-time").last().appendTo($("div#user-message").last());
                                                            $(".gwc-chat-message-time").attr(
                                                                {
                                                                    "aria-atomic": "true",
                                                                }
                                                            );
                                                            $('.gwc-chat-message').last().append('<img class="gwc-chat-head-icon" src="https://www.hel.fi/static/helsinki/chat/user_black.svg"' + helfiChat_user + '/>');
                                                            var link = $("div#user-message").last().html().replace(/((([A-Za-z]{3,9}:(?:\/\/)?)(?:[\-;:&=\+\$,\w]+@)?[A-Za-z0-9\.\-]+|(?:www\.|[\-;:&=\+\$,\w]+@)[A-Za-z0-9\.\-]+)((?:\/[\+~%\/\.\w\-_]*)?\??(?:[\-\+=&;%@\.\w_]*)#?(?:[\.\!\/\\\w]*))?)/g, '<a href="$1" target="_blank">$1</a>');
                                                            $("div#user-message").last().html(link);
                                                        } else {
                                                            $(".gwc-chat-message-time").last().appendTo($("div.gwc-chat-message-text").last());
                                                            $(".gwc-chat-message-time").attr(
                                                                {
                                                                    "aria-atomic": "true",
                                                                }
                                                            );
                                                            // TESTING HEAD-ICON for agent
                                                            $('.gwc-chat-message').last().prepend(helfiChatAgentElement);
                                                            // $('.gwc-chat-message').last().prepend($('#preload').find('.gwc-chat-head-icon'));
                                                            // $('#preload').append('<img class="gwc-chat-head-icon" style="width:35px !important; height:40px !important;" src="/wps/theme/themes/html/eservices_main_theme/img/agent_blue.svg"' + helfiChat_agent + '/>');
                                                            $(".gwc-chat-message-author").last().siblings(".gwc-chat-message-text").each(
                                                                function () {
                                                                    if ($(this).text().trim().length < 6) {
                                                                        $(this)[0].parentElement.remove();
                                                                    }
                                                                }
                                                            );
                                                        }
                                                    }
                                                );
                                                session.onSessionEnded(
                                                    function () {
                                                            $("textarea").attr("disabled", "true");
                                                            $(".gwc-chat-btn").remove();
                                                    }
                                                );
                                            }
                                        ).fail(
                                            function (par) {
                                                alert(par.description);
                                            }
                                        );
                                    }
                                );
                            }
                        }
                    }
                };
                _genesys.cobrowse = false;
            } //helFiChat_localization


            function initHelFiChatLocalization_new()
            {
                if (helFiChat_localization && helFiChat_variant == 'new') {
                    var win = $(this);
                    //after page has been loaded, initialize chat:
                    setTimeout(
                        function () {
                            //var elem = document.getElementsByClassName("gcb-startChat")[0];
                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('tabindex', '0');
                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('onkeypress', 'onEnter(event, this)');
                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('role', 'button');
                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-expanded', 'false');
                            /* WEBSITE VERSION:
                            if (win.width() <= 575 || win.height() <= 425) {
                            */
                            /* OFFLINE VERSION: */
                            if (window.innerWidth <= 575 || window.innerHeight <= 425) {
                                //For checking if window is minimized to not show X button
                                if (!$("#minimize").hasClass("flip")) {
                                    $(".gcb-startBtnsContainer").prepend(helfiChatButtonCloseElementFromMobileView);
                                }

                                $('.gwc-chat-icon-iks').click(
                                    function () {
                                        $(".gcb-startBtnsContainer").prepend(helfiChatButtonCloseElementFromMobileView);
                                    }
                                );

                                if (helFiChat_button.indexOf('open') > 0) {
                                        helFiChat_button = helFiChat_open_button_mobile;
                                } else if (helFiChat_button.indexOf('busy') > 0) {
                                    helFiChat_button = helFiChat_busy_button_mobile;
                                } else {
                                    helFiChat_button = helFiChat_closed_button_mobile;
                                }
                                if (helfiChat_lang == "fi") {
                                    document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-label', 'Chatin käynnistyspainike');
                                } else if (helfiChat_lang == "sv") {
                                    document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-label', 'Chat startknapp');
                                } else {
                                    document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-label', 'Chat Start-button');
                                }
                            } else {
                                if (helfiChat_lang == "en") {
                                    document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-label', 'Open chat');
                                    if (helFiChat_button.indexOf('open') > 0) {
                                          helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-open-en.png" ) !important';
                                          document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chat open');
                                          document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chat open');
                                    } else if (helFiChat_button.indexOf('busy') > 0) {
                                          helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-busy-en.png" ) !important';
                                          document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Queue in the chat');
                                          document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Queue in the chat');
                                    } else {
                                        helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-closed-en.png" ) !important';
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chat Closed');
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chat Closed');
                                    }
                                } else if (helfiChat_lang == "sv") {
                                          document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-label', 'Starta chatten');
                                    if (helFiChat_button.indexOf('open') > 0) {
                                        helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-open-sv.png" ) !important';
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chatten öppen');
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chatten öppen');
                                    } else if (helFiChat_button.indexOf('busy') > 0) {
                                        helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-busy-sv.png" ) !important';
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Kö i chatten');
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Kö i chatten');
                                    } else {
                                        helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-closed-sv.png" ) !important';
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chatten stängd');
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chatten stängd');
                                    }
                                } else {
                                    if (helfiChat_lang == "fi") {
                                        document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-label', 'Avaa chat');
                                        if (!helFiChat_service_availability_info) {
                                                //no availability info available:
                                                document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chatti');
                                                document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chatti');
                                        } else if (helFiChat_button.indexOf('open') > 0) {
                                                  //helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-open.png" ) !important';
                                                  document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chatti avoinna');
                                                  document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chatti avoinna');
                                        } else if (helFiChat_button.indexOf('busy') > 0) {
                                            //helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-busy.png" ) !important';
                                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Jonoa chätissä');
                                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Jonoa chätissä');
                                        } else if (helFiChat_button.indexOf('closed') > 0) {
                                                //helFiChat_button = 'background-image: url( "https://www.hel.fi/static/helsinki/chat/chat-closed.png" ) !important';
                                                document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chatti suljettu');
                                                document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chatti suljettu');
                                        } else {
                                            //no valid chat availability info available:
                                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('alt', 'Chatti');
                                            document.getElementsByClassName("gcb-startChat")[0].setAttribute('title', 'Chatti');
                                        }
                                    }
                                }
                            }
                            if (document.getElementsByClassName("gwc-chat-embedded-window").length != 1) {
                                document.getElementsByClassName("gcb-startChat")[0].setAttribute('style', helFiChat_button);

                                /* DISABLED CHAT BUTTON MODIFICATION FOR OFFLINE TESTING:
                                //hide closed chat button for main page urls only:
                                if(helFiChatPageUrl.toLowerCase() === 'https://www.hel.fi/helsinki/fi' || helFiChatPageUrl.toLowerCase() === 'https://www.hel.fi/helsinki/fi/' || helFiChatPageUrl.toLowerCase() === 'https://www.hel.fi/helsinki/sv' || helFiChatPageUrl.toLowerCase() === 'https://www.hel.fi/helsinki/sv/' || helFiChatPageUrl.toLowerCase() === 'https://www.hel.fi/helsinki/en' || helFiChatPageUrl.toLowerCase() === 'https://www.hel.fi/helsinki/en/'){
                                if(helFiChat_button.indexOf('chat-closed')> -1 || helFiChat_button.indexOf('mobile_closed')> -1){
                                //if chat closed, hide chat button:
                                helFiChat_button = '';
                                if(document.getElementsByClassName('gcb-startChat')[0] !== null){
                                document.getElementsByClassName('gcb-startChat')[0].style.display = 'none';
                                }
                                } //chat-closed
                                } //hide closed chat button for main page urls only
                                */
                            } else {
                                console.log('CHAT ELEMENT NOT LOADED');
                            }
                        }, 1000
                    );
                } // if chat == new
            } //initHelFiChatLocalization

            if (helFiChat_localization && helFiChat_variant == 'new') {
                window.addEventListener('load', initHelFiChatLocalization_new);
            } //helFiChat_localization

            if (helFiChat_localization && helFiChat_variant == 'new') {
                window.addEventListener('load', initHelFiChatLocalization_new);
            } //helFiChat_localization

            if (helFiChat_localization && helFiChat_variant == 'new') {
                $(window).on(
                    'resize', function () {
                        var win = $(this);
                        /* WEBSITE VERSION:
                        bodyHeight = win.height() - 50;
                        if (win.width() <= 575) {
                        $('.gwc-chat-body').height(bodyHeight);
                        } else if (win.height() <= 425) {
                        $('.gwc-chat-body').height(bodyHeight);
                        }
                        */
                        /* OFFLINE VERSION: */
                        bodyHeight = window.innerHeight - 50;
                        if (window.innerWidth <= 575) {
                            $('.gwc-chat-body').height(bodyHeight);
                        } else if (window.innerHeight <= 425) {
                            $('.gwc-chat-body').height(bodyHeight);
                        }
                    }
                );
            }

            function removeChatIcon()
            {
                document.getElementsByClassName("gwc-chat-icon-iks-mobile")[0].style.display = 'none';
                document.getElementsByClassName("gcb-startChat")[0].style.display = 'none';
            }

            function toggleFlip()
            {
                document.getElementsByClassName('gwc-chat-icon-down')[0].classList.toggle("flip");
                $('.gwc-chat-icon-down').attr(
                    'aria-label', function (index, attr) {
                        return attr == arrowUpAriaLabel ? arrowDownAriaLabel : arrowUpAriaLabel;
                    }
                );
                $('.gwc-chat-icon-down').attr(
                    'title', function (index, attr) {
                        return attr == arrowUpTitle ? arrowDownTitle : arrowUpTitle;
                    }
                );
                $('.gwc-chat-icon-down').attr(
                    'alt', function (index, attr) {
                        return attr == arrowUpAlt ? arrowDownAlt : arrowUpAlt;
                    }
                );
                document.getElementsByClassName("gwc-chat-icon-down")[0].setAttribute('aria-expanded', 'true');
                /* set X button on mobile chat invisibile */
                if (document.getElementsByClassName("gwc-chat-icon-iks-mobile").length !== 0) {
                    document.getElementsByClassName("gwc-chat-icon-iks-mobile")[0].style.display = 'none';
                }

                if ($('.gwc-chat-icon-down').hasClass('flip')) {
                    document.getElementsByClassName("gwc-chat-icon-down")[0].setAttribute('aria-expanded', 'false');
                }
            }

            function onEnter(e, el)
            {
                if (e.keyCode === 13) {
                    el.click();
                }
            }

            function addFocus()
            {
                $('textarea.gwc-chat-input').css('outline', '');
            }

            function removeFocus()
            {
                $('textarea.gwc-chat-input').css('outline', 'unset');
            }

            // Catch Chat window events:
            window.onload = function () {
                document.body.onclick = function (e) {
                    e = window.event ? event.srcElement : e.target;
                    // close chat click event:
                    if (e.className && e.className.indexOf('gwc-chat-icon-iks') != -1) {
                          console.log('Event: close chat');

                          /* set back chat button visibility */
                          document.getElementsByClassName("gcb-startChat")[0].style = helFiChat_button;
                          document.getElementsByClassName("gcb-startChat")[0].setAttribute('aria-expanded', 'false');
                        if (document.getElementsByClassName("gwc-chat-icon-iks-mobile").length !== 0) {
                            document.getElementsByClassName("gwc-chat-icon-iks-mobile")[0].style.display = 'block';
                        }
                    }
                    if (e.className && e.className.indexOf('gwc-chat-icon-iks-mobile') != -1) {
                        document.getElementsByClassName("gwc-chat-icon-iks-mobile")[0].style.display = 'none';
                        document.getElementsByClassName("gcb-startChat")[0].style.display = 'none';
                    }
                }
            }
        }
    );
})(jQuery);
