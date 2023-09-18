function isEmpty(str) {
    return (!str || 0 === str.length);
}

function isBlank(str) {
    return (!str || /^\s*$/.test(str));
}

String.prototype.isEmpty = function() {
    return (this.length === 0 || !this.trim());
};

function getCookieChat(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return "";
}

function callShibboleth()
{
    var interactionId = '';
    interactionId = getCookieChat("gcReturnSessionId");
	//Current url without querystring:
	var currentPage = location.toString().replace(location.search, "");
	var shibbolethString = "https://chat-proxy.hel.fi/chat/tunnistus/Shibboleth.sso/KAPALogin?";
	shibbolethString += "target=";
    shibbolethString += "https://chat-proxy.hel.fi/chat/tunnistus/MagicPagePlain/ReturnProcessor";
	console.log('currentPage:'+currentPage);
    shibbolethString += "%3ForigPage%3D" + currentPage + "?dir%3Din%26gcLoginButtonState%3D1%26errcode%3d0";
	shibbolethString += "%26" + "interactionId" + "%3D" + interactionId;
	window.location = shibbolethString;
}

var _genesys = {
    onReady: [],
    chat: {
        registration: false,
        localization : 'https://chat-proxy.hel.fi/gms/sote/localization/chat-testisivu-fi.json',
        onReady: [],
        ui: {
            onBeforeChat: function (chat) {
                _genesys.chat.onReady.push(function (chatWidgetApi) {
                    chatWidgetApi.restoreChat({
                        serverUrl: "https://chat-proxy.hel.fi/chat/sote/cobrowse",
                        registration: function (done) {
                            done({
                                 service: 'TESTISIVU_TESTI'
                            });
                        }
                    }).done(function (session) {
                        session.setUserData({
                           service: 'TESTISIVU_TESTI'
                        });
                    }).fail(function (par) {
                        alert(par.description);
                    });
                });
            }
        }
    }
};
_genesys.cobrowse = false;


var url = window.location.search;
url = decodeURIComponent(url);
var referringURL = '';
var returnURL = '';

/* FILL HERE DRUPAL URL SCOPE UNDER WHICH SUUNTE CHAT IS RUN, COOKIE WOULD BE GOOD TO HAVE URL CONTEXT AS WELL IF MULTIPLE GENESYS CHATS ARE RUN UNDER SAME DOMAIN */
var helfiChatCookiePath = '/fi/sosiaali-ja-terveyspalvelut/';

// show authenticate button 0 no, 1 yes:
var int_gcLoginButtonState=0;

// dir = out => transfer to authentication
if(url.indexOf('?dir=out') !== -1){
  referringURL = document.referrer;
  //setting helper cookie return url:
  document.cookie = "gcReturnUrl="+referringURL+";path="+helfiChatCookiePath;
  callShibboleth();
}

// dir = in => transfer to back to hel.fi -site from authentication
if(url.indexOf('?dir=in') !== -1){
  // set gcLoginButtonState -info cookie, if user has authenticated=1, or not..
    var now = new Date();
    var time = now.getTime();
   int_gcLoginButtonState=1;
    time += 180 * 1000;
    now.setTime(time);
   document.cookie = "gcLoginButtonState="+int_gcLoginButtonState +"; expires=" + now.toUTCString() +";path="+helfiChatCookiePath;

  // get return url back from hel.fi cookie:
  returnURL = getCookieChat("gcReturnUrl");

  // redirect urser back from Vetuma, to hel.fi -site:
  // prevent endless loop, do not redirect back to this transfer page itself!
  if(returnURL!="" && returnURL.indexOf('transfer') == -1 && !isEmpty(returnURL) && !isBlank(returnURL)){
     window.location.href = returnURL + '?redir=done';
  }
  else{
    // search alternative returnURl cookie, set by hel.fi chat page before user clicked authenticate link:
     returnURL = getCookieChat("gcAlternativeReturnUrl");
       if(returnURL!="" && returnURL.indexOf('transfer') == -1 && !isEmpty(returnURL) && !isBlank(returnURL)){
          window.location.href = returnURL + '?redir2=done';
      }
    // default fallback: some error happened. Redirect back to top level contextual main page:
     window.location = helfiChatCookiePath + '?redir3=done';
  }
}
