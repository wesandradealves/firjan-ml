/**
 * @file
 * Simple countdown used on the download form.
 */

(function (Drupal) {

  'use strict';

  Drupal.emeCoundown = Drupal.emeCoundown || {};
  Drupal.emeCoundown.intervals = Drupal.emeCoundown.intervals || {};

  Drupal.behaviors.emeCountdown = {
    attach: function attach(context) {
      var elements = context.getElementsByClassName('js-eme-countdown');
      if (elements.length) {
        for (var i = 0, max = elements.length; i < max; i++) {
          if (
            elements[i].hasAttribute('data-processed') ||
            Number(parseFloat(elements[i].textContent)) != elements[i].textContent
          ) {
            continue;
          }

          elements[i].setAttribute('data-processed', 'data-processed');
          Drupal.emeCoundown.intervals[i] = setInterval(function (element, i) {
            var current = parseInt(element.textContent, 10) - 1;
            element.textContent = current;
            if (current < 1) {
              clearInterval(Drupal.emeCoundown.intervals[i]);
            }
          }, 1000, elements[i], i);
        }
      }
    }
  };

})(Drupal);
