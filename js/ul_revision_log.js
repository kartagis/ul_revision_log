/**
 * @file
 * Gray out/disable the "Save and Publish" button and "Save and Archieve" button
 * if the revivion log message is empty
 **/

(function ($, window, Drupal) {

  Drupal.behaviors.ulRevisionLog = {
    attach: function attach(context, settings) {
      $publish = $('#edit-actions li.moderation-state-published input.form-submit');
      $archive = $('#edit-actions li.moderation-state-archived input.form-submit');
      $log = $('#edit-revision-log-0-value');

      if(!$log.val()) {
        $log.attr("placeholder", "Please enter 16 characters or more.");
      }
      // Call help function to disable/enable Publish button.
      handleSubmitButtonForLog($publish, $log);
      // Call help function to disable/enable Archive button.
      handleSubmitButtonForLog($archive, $log);
    }
  }

  /**
   * Gray out/disable Pulish and Archive buttons; Enable them when inputing log message.
   *
   * @param object $publish
   * @param object $log
   */
  function handleSubmitButtonForLog ($publish, $log) {
    if ($.trim($log.val()).length < 16 ) {
      $publish.attr('disabled', 'disabled').addClass('gray-btn');
    }
    // Enable the Publish/Archive button after typing text in the log message.
    $log.keyup(function(event) {
      charLength = $(this).val().length;
      if (charLength >= 16 ) {
        if ($publish.hasClass('gray-btn')) {
            $publish.removeAttr('disabled', 'disabled').removeClass('gray-btn');
        }
      }
      else {
        if (!$publish.hasClass('gray-btn')) {
          $publish.attr('disabled', 'disabled').addClass('gray-btn');
        }
      }
    });
  }

})(jQuery, window, Drupal);
