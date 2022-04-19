'use strict';

(function ($, Drupal) {
  Drupal.behaviors.events_list = {
    attach: function attach(context, settings) {
      if(settings.helfi_events.eventsUrl) {
        Drupal.behaviors.events_list.getEvents(settings.helfi_events.eventsUrl, true);
      }
    },
    listToHtml: function listToHtml(list) {
      const currentLanguage = drupalSettings.path.currentLanguage;

      // Bail if no current language
      if(!currentLanguage) {
        return;
      }

      return  list.map(event => {
        // If event name is not in current language, return early
        if(!event.name[currentLanguage]) {
          return;
        }

        const startDate = new Date(event.start_time);

        // Base element for event, wihout text elements from api
        const eventElement = $(`
          <div class="event">
            <a class="event__wrapper" href="${drupalSettings.helfi_events.baseUrl}/events/${event.id}">
              <div class="event__image-container">
                <div class="event__tags event__tags--mobile" role="Region" aria-label="${Drupal.t('Event keywords')}">
                </div>
              </div>
              <div class="event__content-container">
                <h3 class="event__name"></h3>
                <div class="event__content event__content--date">
                  <div class="event__icon event__icon--location">
                    <span class="hel-icon hel-icon--clock"></span>
                  </div>
                  <div class="event__date">
                    ${startDate.toLocaleDateString('fi-FI')} ${startDate.toLocaleTimeString('fi-FI')}
                  </div>
                </div>
                <div class="event__content event__content--location">
                  <div class="event__icon event__icon--location">
                    <span class="hel-icon hel-icon--location"></span>
                  </div>
                  <div class="event__location"></div>
                </div>
                <div class="event__lower-container">
                  <div class="event__tags event__tags--desktop role="Region" aria-label="${Drupal.t('Event keywords')}">
                  </div>
                  <span
                    class="link__type link__type--external"
                    aria-label="(${Drupal.t('Link leads to external service', {}, {context: 'Explanation for screen-reader software that the icon visible next to this link means that the link leads to an external service.'})})">
                  </span>
                </div>
              </div>
            </a>
          </div>
        `);

        // Escape and append text content from Api response
        const keywords = event.keywords.map(keyword => {
          if(keyword.name[currentLanguage]) {
            return $('<span></span>').text(keyword.name[currentLanguage]);
          }
        });
        if(keywords.length) {
          $(eventElement).find('.event__tags').append(keywords);
        }

        const eventName = document.createTextNode(event.name[currentLanguage]);
        $(eventElement).find('h3').append(eventName);

        // Use first image or fallback to placeholder if no images present
        const imageUrl = (event.images.length && event.images[0].url) ? event.images[0].url : '';
        const imageElement = (event.images.length && event.images[0].url) ?
          `<img class="event__image" alt="${eventName.textContent.trim()}" src="${event.images[0].url}"></img>` :
          `<div class="event__image image-placeholder">
            <span class="hel-icon hel-icon--heart fill"></span>
          </div>`;
        $(eventElement).find('.event__image-container').append(imageElement);

        const location = `${event.location.name[currentLanguage]}${event.location.street_address ? ', ' + event.location.street_address[currentLanguage] : ''}`;
        $(eventElement).find('.event__location').append(document.createTextNode(location))

        return eventElement;
      });
    },
    getEvents: function getEvents(url, initial = false) {
      function get404() {
        return `
          <div>
            <h3>${Drupal.t('This event list is empty.')}</h3>
            <p>${Drupal.t('No worries though, this city does not run out of things to do.')}</p>
            <a class="hds-button hds-button--primary" href="${drupalSettings.helfi_events.baseUrl}">
              <span class="hds-button__label">${Drupal.t('See all events')}</span>
              <span
                class="link__type link__type--external"
                aria-label="(${Drupal.t('Link leads to external service', {}, {context: 'Explanation for screen-reader software that the icon visible next to this link means that the link leads to an external service.'})})">
              </span>
            </a>
          </div>
        `; 
      }

      function setLoading(state = false) {
        const progressElement = Drupal.theme('ajaxProgressThrobber');

        if(state === true) {
          $('.event-list__load-more .load-more-button').attr('disabled', true);
          $('.component--event-list .event-list__list-container').append(progressElement);
        }
        else {
          $('.event-list__load-more .load-more-button').removeAttr('disabled');
          $('.component--event-list .hds-loading-spinner').remove();
        }
      }
      
      setLoading(true);

      const events = fetch(url, {
        method: 'GET',
        headers:  {
          'Content-type': 'application/json'
        }
      })
      .then(res => res.json())
      .then(json => {
        if(json && json.meta.count > 0) {
          $('.component--event-list .event-list__count').html(`<strong>${json.meta.count}</strong> ${Drupal.t('events')}`);
          const listHtml = Drupal.behaviors.events_list.listToHtml(json.data);
          $('.component--event-list .event-list__list-container').append(listHtml);

          const next = json.meta.next ?? null;

          if(next) {
            if($('.event-list__load-more .load-more-button').length) {
              $('.event-list__load-more .load-more-button').attr('onClick', Drupal.behaviors.events_list.getEvents(next))
            }
            else {
              $('.event-list__load-more').append(`
                <button class="hds-button hds-button--primary load-more-button" onClick="Drupal.behaviors.events_list.getEvents('${next}')">
                  <span class="hds-button__label">${Drupal.t('Load more events')}</span>
                </button>
                `
              )
            }
            if(!$('.event-list__load-more .refine-button').length) {
              $('.event-list__load-more').append(`
                <a class="hds-button hds-button--secondary" href="${drupalSettings.helfi_events.initialUrl}">
                  <span class="hds-button__label">${Drupal.t('Refine search in tapahtumat.hel.fi')}</span>
                  <span
                    class="link__type link__type--external"
                    aria-label="(${Drupal.t('Link leads to external service', {}, {context: 'Explanation for screen-reader software that the icon visible next to this link means that the link leads to an external service.'})})">
                  </span>
                </a>
                `
              )
            }
          }
          else if($('.event-list__load-more .load-more-button').length) {
            $('.event-list__load-more .load-more-button').remove();
          }
        }
        else if(initial) {
          $('.component--event-list .event-list__list-container').append(get404());
        }
      })
      .catch(e => console.error(e))
      .finally(() => setLoading(false));
    },
  }
})(jQuery, Drupal);