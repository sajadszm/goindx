<div id="wp-quickotp-container" class="wp-quickotp-container-simple">
    <form id="wp-quickotp-form" action="" method="post">
        <p class="wp-quickotp-form-row">
            <label for="wp-quickotp-phone"><?php _e( 'Mobile Number', 'wp-quickotp' ); ?></label>
            <input type="text" name="phone" id="wp-quickotp-phone" class="input" value="" placeholder="09123456789">
        </p>
        <p class="wp-quickotp-form-row">
            <input type="submit" name="wp-quickotp-submit" id="wp-quickotp-submit" class="button button-primary" value="<?php _e( 'Send OTP', 'wp-quickotp' ); ?>">
        </p>
    </form>
    <div id="wp-quickotp-otp-container" style="display: none;">
        <form id="wp-quickotp-otp-form" action="" method="post">
            <p class="wp-quickotp-form-row">
                <label for="wp-quickotp-otp"><?php _e( 'OTP', 'wp-quickotp' ); ?></label>
                <input type="text" name="otp" id="wp-quickotp-otp" class="input" value="">
            </p>
            <p class="wp-quickotp-form-row">
                <input type="submit" name="wp-quickotp-otp-submit" id="wp-quickotp-otp-submit" class="button button-primary" value="<?php _e( 'Login', 'wp-quickotp' ); ?>">
            </p>
        </form>
        <div id="wp-quickotp-timer"></div>
    </div>
    <div id="wp-quickotp-message"></div>
</div>
