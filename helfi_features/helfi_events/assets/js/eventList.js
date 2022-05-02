'use strict';

(function ($) {
  Drupal.behaviors.events_list = {
    attach: function attach(context, settings) {
      if(settings.helfi_events.eventsUrl) {
        Drupal.behaviors.events_list.getEvents(settings.helfi_events.eventsUrl, true);
      }
    },
    listToHtml: function listToHtml(list) {
      const currentLanguage = drupalSettings.path.currentLanguage;
      const {
        at,
        events,
        eventKeywords,
        externalLink,
        seeAll
      } = drupalSettings.helfi_events.translations;

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
        const endDate = new Date(event.end_time);

        // Base element for event, wihout text elements from api
        const eventElement = $(`
          <div class="event-list__event">
            <a class="event-list__events-container" href="${drupalSettings.helfi_events.baseUrl}/events/${event.id}" aria-label="(${externalLink})">
              <div class="event-list__image-container">
                <div class="event-list__tags event-list__tags--mobile" role="Region" aria-label="${eventKeywords}">
                </div>
              </div>
              <div class="event-list__content-container">
                <h3 class="event-list__event-name"></h3>
                <div class="event__content event__content--date">
                  <div class="event__date">
                    ${startDate.toLocaleDateString('fi-FI')}, ${at}
                    ${startDate.toLocaleTimeString('fi-FI', {hour: '2-digit', minute: '2-digit'})}
                    -
                    ${endDate.toLocaleTimeString('fi-FI', {hour: '2-digit', minute: '2-digit'})}
                  </div>
                </div>
                <div class="event__content event__content--location">
                  <div class="event__location"></div>
                </div>
                <div class="event__lower-container">
                  <div class="event-list__tags event-list__tags--desktop role="Region" aria-label="${eventKeywords}">
                  </div>
                  <span class="link__type link__type--external event-list__event-link-indicator">
                  </span>
                </div>
              </div>
            </a>
          </div>
        `);

        // Escape and append text content from Api response
        const keywords = event.keywords.map(keyword => {
          if(keyword.name[currentLanguage]) {
            const keywordName = keyword.name[currentLanguage];
            // Api return names sometimes in lowercase, capitalize it here instead of CSS for accessibility
            return $('<span class="event-list__tag"></span>').text(keywordName.charAt(0).toUpperCase() + keywordName.slice(1));
          }
        });
        if(keywords.length) {
          $(eventElement).find('.event-list__tags').append(keywords);
        }

        const eventName = document.createTextNode(event.name[currentLanguage]);
        $(eventElement).find('.event-list__event-name').append(eventName);

        // Use first image or fallback to placeholder if no images present
        const imageUrl = (event.images.length && event.images[0].url) ? event.images[0].url : null;
        const imageAlt = imageUrl && event.images[0].alt_text ? event.images[0].alt_text : eventName.textContent.trim();
        const imageElement = imageUrl ?
          `<img class="event-list__event-image" alt="${imageAlt}" src="${event.images[0].url}"></img>` :
          $(drupalSettings.helfi_events.imagePlaceholder).addClass('event-list__event-image');
        $(eventElement).find('.event-list__image-container').append(imageElement);

        const location = `${event.location.name[currentLanguage]}${event.location.street_address ? ', ' + event.location.street_address[currentLanguage] : ''}`;
        $(eventElement).find('.event__location').append(document.createTextNode(location))

        return eventElement;
      });
    },
    getEvents: function getEvents(url, initial = false) {
      const {
        emptyList,
        emptyListSubText,
        eventsCount,
        externalLink,
        loadMore,
        refineSearch,
      } = drupalSettings.helfi_events.translations;

      function get404() {
        return $(`
          <div>
            <h3>${emptyList}</h3>
            <p class="events-list__empty-subtext">${emptyListSubText}</p>
          </div>
        `).append(drupalSettings.helfi_events.seeAllButton);
      }

      function setLoading(state = false) {
        const progressElement = $('<div>', {class: 'event-list-spinner'}).append($(Drupal.theme('ajaxProgressThrobber')));

        if(state === true) {
          $('.event-list__load-more-button').attr('disabled', true);
          $('.event-list__list-container').append(progressElement);
        }
        else {
          $('.event-list__load-more-button').removeAttr('disabled');
          $('.event-list-spinner').remove();
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
          $('.event-list__count').html(`<strong>${json.meta.count}</strong> ${eventsCount}`);
          const listHtml = Drupal.behaviors.events_list.listToHtml(json.data);
          $('.event-list__list-container').append(listHtml);

          const next = json.meta.next ?? null;

          if(next) {
            if($('.event-list__load-more-button').length) {
              $('.event-list__load-more-button').attr('onClick', Drupal.behaviors.events_list.getEvents(next))
            }
            else if(drupalSettings.helfi_events.loadMore) {
              $('.event-list__load-more').append(`
                <button class="hds-button hds-button--primary event-list__load-more-button" onClick="Drupal.behaviors.events_list.getEvents('${next}')">
                  <span class="hds-button__label">${loadMore}</span>
                </button>
                `
              )
            }
            if(!$('.event-list__refine-button').length) {
              $('.event-list__load-more').append(drupalSettings.helfi_events.refineSearchButton);
            }
          }
          else if($('.event-list__load-more-button').length) {
            $('.event-list__load-more-button').remove();
          }
        }
        else if(initial) {
          $('.event-list__list-container').append(get404());
        }
      })
      .catch(e => console.error(e))
      .finally(() => setLoading(false));
    },
  }
})(jQuery);
