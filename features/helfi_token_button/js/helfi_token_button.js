(function ($) {
  Drupal.behaviors.helfiTokenButtonBehaviour = {
    attach: function (context, settings) {
      $(context).find('.item-container').on('click', '.field', function () {
        var eid = $('.item-container').attr('data-entity-id');
        var classList = $(this).attr("class");
        var classArray = classList.split(" ");
        var fieldName = classArray[1].split("field--name-").pop();

        var token = '[aet:' + eid + ':' + fieldName + ']';
        $(context).find('input#edit-field-token-0-value').val(token);
      });
    }
  };

})(jQuery);
