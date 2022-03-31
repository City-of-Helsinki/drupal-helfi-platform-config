'use strict';

(function ($, Drupal) {
  Drupal.behaviors.events_list = {
    attach: function attach(context, settings) {
      var next;

      function listToHtml(list) {
        return  list.map(event => 
          `
            <div class="event">
              <img src="${(event.images.length && event.images[0].url) ? event.images[0].url : ''}" />
              <h2>${event.name.fi}</h2>
              <div>${event.start_time}</div>
              <div>${event.location.name.fi}${event.location.street_address ? ', ' + event.location.street_address.fi : ''}</div>
              <div>
                ${event.keywords.map(keyword => `<span>${keyword.name.fi}</span>`)}
              <div>
            </div>
          `
        );
      }

      if(settings.helfi_events.eventsUrl) {
        const events = fetch(settings.helfi_events.eventsUrl, {
          method: 'GET',
          headers:  {
            'Content-type': 'application/json'
          }
        })
          .then(res => res.json())
          .then(json => {
            if(json && json.meta.count > 0) {
              const listHtml = listToHtml(json.data);
              $('.paragraph--type--event-list').append(listHtml);
            }
          })
      }
    }
  }
})(jQuery, Drupal);