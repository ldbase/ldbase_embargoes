(function ($, Drupal) {
  Drupal.behaviors.embargoes = {
    attach: function (context, settings) {
      $('div.field--type-file a').addClass('restricted-access');
      $('div.field--type-file a').text('Access to this file is restricted.');
      $('div.field--type-file a').attr('title', 'Access to this file is restricted.');
    }
  };
})(jQuery, Drupal);
