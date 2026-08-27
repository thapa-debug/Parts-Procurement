<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    /*
    |--------------------------------------------------------------------------
    | UI copy — login screen
    |--------------------------------------------------------------------------
    */

    'login' => [
        'title' => 'Log In',
        'heading' => 'Sign in to your account',
        'subheading' => 'Enter your email and password to continue.',
        'email_label' => 'Email',
        'password_label' => 'Password',
        'remember_label' => 'Remember me',
        'submit' => 'Log In',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI copy — forced password-change screen
    |--------------------------------------------------------------------------
    */

    'password_change' => [
        'title' => 'Set a New Password',
        'heading' => 'Set a new password',
        'subheading' => 'You are signing in with a temporary password. Choose a new one to continue.',
        'current_password_label' => 'Current password',
        'new_password_label' => 'New password',
        'confirm_password_label' => 'Confirm new password',
        'submit' => 'Update password',
        'success' => 'Your password has been updated.',
    ],

];
