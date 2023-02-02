(function ($) {
  /**
   * Function that adds column sizes to column titles based on
   * the selected design.
   *
   * @param {Object} design
   *   Selected design.
   * @param {Object} leftColumn
   *   Left column.
   * @param {Object} rightColumn
   *   Right column.
   */
  function toggleFields(design, leftColumn, rightColumn) {
    // Get the selected design.
    let selectedDesign = design.val();
    let left = '';
    let right = '';

    // Apply the titles for the columns size element.
    if (selectedDesign === '50-50') {
      left = '50%';
      right = '50%';
    } else if (selectedDesign === '30-70') {
      left = '30%';
      right = '70%';
    } else {
      left = '70%';
      right = '30%';
    }

    leftColumn.find('.columns_size').text(left);
    rightColumn.find('.columns_size').text(right);
  }

  /**
   * The function that goes through each paragraph that is type Columns
   * and toggles the fields using the designSelection function.
   */
  function loopThroughParagraphs() {
    // Find all the Columns paragraphs.
    let paragraphs = $('.paragraph-type--columns');

    // Go through each paragraph.
    paragraphs.each(function () {
      // Find the design for the paragraph in question.
      let design = $(this).find('.field--name-field-columns-design select');
      let leftColumn = $(this).find('.field--name-field-columns-left-column');
      let rightColumn = $(this).find('.field--name-field-columns-right-column');

      // Find the column paragraphs and add columns_size element if necessary.
      if (leftColumn.find('.columns_size').length === 0) {
        leftColumn
          .find(
            'table.field-columns-left-column-values > thead .form-item__label'
          )
          .after('<span class="columns_size"></span>');
      }
      if (rightColumn.find('.columns_size').length === 0) {
        rightColumn
          .find(
            'table.field-columns-right-column-values > thead .form-item__label'
          )
          .after('<span class="columns_size"></span>');
      }

      // Run the toggle function for fields
      toggleFields(design, leftColumn, rightColumn);

      // On design value change, run the toggle function again for the fields.
      design.change(function () {
        toggleFields(design, leftColumn, rightColumn);
      });
    });
  }

  /**
   * Each time ajax is run on the node-edit form we need to also trigger the
   * toggle for the fields according to the design.
   */
  $(document).ajaxComplete(() => {
    loopThroughParagraphs();
  });

  /**
   * When the dom is loaded show & hide the elements according to the design.
   */
  $(document).ready(function () {
    loopThroughParagraphs();
  });
})(jQuery);
