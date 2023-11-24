export default class EuCookieManager {
  cookieCheck(cookieNames) {
    let cookiesOk = true;
    cookieNames.map((cookieName) => {
      if (!Drupal.eu_cookie_compliance.hasAgreedWithCategory(cookieName)) cookiesOk = false;
    });
    return cookiesOk;
  }

  cookieSet() {
    if (Drupal.eu_cookie_compliance.hasAgreedWithCategory('chat')) return;

    Drupal.eu_cookie_compliance.setAcceptedCategories([ ...Drupal.eu_cookie_compliance.getAcceptedCategories(), 'chat' ]);
  }
}
