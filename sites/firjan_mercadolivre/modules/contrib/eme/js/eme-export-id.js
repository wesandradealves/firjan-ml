/**
 * @file
 * Simple countdown used on the download form.
 */

(function ($, Drupal, once) {

  Drupal.emeExportId = Drupal.emeExportId || {};

  Drupal.behaviors.emeExportId = {
    attach: function attach(context, settings) {
      once('eme-export-id', $('[data-eme-export-id]'), context).forEach(function (element) {
        var emeId = $(element).attr('data-eme-export-id');
        if (
          !emeId ||
          !settings.emeExport ||
          !settings.emeExport[emeId] ||
          !settings.emeExport[emeId].source ||
          !settings.emeExport[emeId].destination
        ) {
          return true;
        }

        Drupal.emeExportId[emeId] = {};
        var source = settings.emeExport[emeId].source;

        Object.keys(settings.emeExport[emeId].source).forEach(function (selector) {
          Drupal.emeExportId[emeId][settings.emeExport[emeId].source[selector]] = $(selector).val() || $(selector).attr('placeholder');
          $(selector).on('input', function (event) {
            Drupal.emeExportId[emeId][settings.emeExport[emeId].source[selector]] = event.target.value || $(event.target).attr('placeholder');
            Drupal.emeUpdate(emeId, settings.emeExport[emeId].destination);
          });
        });
      });
    }
  };

  /**
   * Updates placeholders.
   *
   * @param {string} emeId
   * @param {object} destinations
   */
  Drupal.emeUpdate = function (emeId, destinations) {
    Object.keys(destinations).forEach(function (selector) {
      var newPlaceholder = $.isArray(destinations[selector])
        ? destinations[selector][0]
        : destinations[selector];

      Object.keys(Drupal.emeExportId[emeId]).forEach(function (valuekey) {
        var rpl = '\\(' + valuekey + '\\)';
        newPlaceholder = newPlaceholder.replace(( new RegExp(rpl, "g")), Drupal.emeExportId[emeId][valuekey]);
      });

      if ($.isArray(destinations[selector])) {
        newPlaceholder = newPlaceholder.replace(/^\w/, function (firstLetter) {
          return firstLetter.toUpperCase();
        }).replace(/_+/, ' ');
      }

      $(selector).attr('placeholder', newPlaceholder);
    });
  };

})(jQuery, Drupal, once);
