/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  (function ($, Drupal, once) {
    Drupal.behaviors.slickCarrousel = {
      attach: function (context, settings) {
        $('.views-video-container').each(function(){
          $(this).slick({
            dots: false,
            infinite: true,
            speed: 300,
            slidesToShow: 3,
            slidesToScroll: 1,
            centerMode: false,
            responsive: [
              {
                breakpoint: 1024,
                settings: {
                  slidesToShow: 3,
                  slidesToScroll: 1,
                }
              },
              {
                breakpoint: 768,
                settings: {
                  slidesToShow: 2,
                  slidesToScroll: 1
                }
              },
              {
                breakpoint: 480,
                settings: {
                  slidesToShow: 1,
                  slidesToScroll: 1
                }
              }
              // You can unslick at a given breakpoint now by adding:
              // settings: "unslick"
              // instead of a settings object
            ]
          })
        })
      }
    };
  })(jQuery, Drupal, once);

  (function ($, Drupal, once) {
    Drupal.behaviors.accordionTrigger = {
      attach: function (context, settings) {
        $('.accordion-item').each(function(){
          $(this).find('.arrow').on('click', function(){
            $('.views-video-container').each(function(){
              $(this).slick('setPosition');
            })
          });
        })
      }
    };
  })(jQuery, Drupal, once);


})(jQuery, Drupal);
