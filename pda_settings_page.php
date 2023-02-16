<?php require_once(ABSPATH . 'wp-includes/pluggable.php'); ?>
<h2>PDAcademy Settings</h2>
<p>These are used to connect to the PDAcademy Moodle and perform webservice tasks through the signupstoken web service. Requires a webservice token, which has to be set up on the Moodle site first.</p>
<form method="post" action="options.php">
    <?php
        settings_fields( 'pda-settings-group' );
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">Moodle Root URL</th>
            <td><input type="text" name="pda_moodle" value="<?php echo get_option('pda_moodle'); ?>" size="60" /></td>
        </tr>
        <tr>
            <th scope="row">Webservice Token</th>
            <td><input type="text" name="pda_token" value="<?php echo get_option('pda_token'); ?>" size="60" /></td>
        </tr>
    </table>
    <p class="submit">
        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
</form>

<h2>PDAcademy Token Shortcode</h2>
<p>You can insert a form to verify the token code and apply it to the current user. A form containing a field and button is rendered, and associated scripts put in place. Each element in the form has a unique css class name for styling. In its most basic form all you need is to add the shortcode:</p>
<p><code>[pdaverify]Submit[/pdaverify]</code></p>
<p>The text inside the shortcode becomes the text that is on the button (html & other shortcodes are ok here as well). It has some other possible attributes to use to override the default text values:</p>
<blockquote>
<ul>
    <li><strong>feedbackObj</strong> : the class or id selector of an object to contain feedback from the plugin (default #feedbackObj)</li>
    <li><strong>placeholder</strong> : the input placeholder text (shown if the input is empty)</li>
    <li><strong>label</strong> : Label which appears next to text</li>
    <li><strong>size</strong> : Size attribute for input control<li>
</ul>
</blockquote>
<p><code>[pdaverify feedbackObj=".some-class" placeholder="Jeff Bezos" label="Who owns Amazon?" size="5"]Do It![/pdaverify]</code></p>
