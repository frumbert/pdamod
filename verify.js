/*
 * purpose: to submit the form back to the plugin handler and verify+apply the token against moodle
 *
 * included automatically when you use the [pdaverify] shortcode
 */
jQuery(document).ready( function() {
    Array.from(document.querySelectorAll("form.pda-verify-wrapper")).forEach((el) => {
        el.addEventListener("submit", (e) => {
            e.preventDefault();
            const outputField = document.querySelector(el.dataset.pdaFeedbackObj);
            jQuery.ajax({
                type: 'post',
                dataType: "json",
                url: pdaAjax.ajaxurl,
                data: jQuery(el).serialize(),
                success: function(response) {
                    outputField.textContent = response.output;
                    if (response.error > 0) {
                        alert('Sorry, an error occurred.');
                        console.error(response);
                    }
                }
            });
        });
    });
});