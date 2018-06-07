<?php

define('BASELINE_DOMAIN', 'http://mysite-baseline.test');
define('BRANCH_DOMAIN', 'http://mysite.test');

define('SCREENSHOT_BASELINE', true);
define('SCREENSHOT_BRANCH', true);
define('CREATE_RESULTS', true);

define('SHOW_DEBUG', false);

define('PATHS', serialize([
    '/',
    '/contact-us/',
    '/admin'
]));

// Admin credentials
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'password');

// ID's of elements on /Security/login
define('LOGIN_USERNAME_ID', 'MemberLoginForm_LoginForm_Email');
define('LOGIN_PASSWORD_ID', 'MemberLoginForm_LoginForm_Password');
define('LOGIN_SUBMIT_ID', 'MemberLoginForm_LoginForm_action_dologin');

