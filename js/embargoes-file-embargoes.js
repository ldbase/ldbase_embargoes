(function ($, Drupal) {
  Drupal.behaviors.embargoes = {
    attach: function (context, settings) {
      $('div.field--type-file a').css('color', 'red');
      $('div.field--type-file a').css('background-color', 'white');
      $('div.field--type-file a').text('Access to this file is restricted.');
      $('div.field--type-file a').attr('title', 'Access to this file is restricted.');
    }
  };
})(jQuery, Drupal);
