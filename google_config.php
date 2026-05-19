<?php
// GOOGLE API CONFIGURATION
$google_client_id     = '';
$google_client_secret = '';
$google_redirect_url  = 'http://localhost/pgnest/google_callback.php';

// Create the Login Link
$google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile') . '&redirect_uri=' . urlencode($google_redirect_url) . '&response_type=code&client_id=' . $google_client_id . '&access_type=online';
?>