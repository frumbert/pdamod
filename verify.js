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
                data: jQuery(el).serialize()
            }).done(function(response) {
            console.dir(response);
                if (response.hasOwnProperty('message') && response.message) {
                    outputField.textContent = response.message;
                } else {
                    el.querySelector('input[name="token"]').value = '';
                    outputField.innerHTML = "Applied token. It might take a minute before the courses appear. Reloading in <span>10</span> seconds";
                    let i = 10;
                    let si = setInterval(function() {
                        outputField.querySelector('span').textContent = --i;
                        if (i < 1) {
                            clearTimeout(si);
                            location.reload();
                        }
                    },1000);
                }
            }).fail(function (xhr,status,err) {
                console.log(xhr,status,err);
            });
        });
    });
});