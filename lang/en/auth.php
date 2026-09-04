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
        'email_placeholder' => 'you@example.com',
        'password_label' => 'Password',
        'remember_label' => 'Remember me',
        'submit' => 'Log In',
        'no_account' => "Don't have an account?",
        'register_link' => 'Sign up',
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

    /*
    |--------------------------------------------------------------------------
    | UI copy — buyer self-registration screen
    |--------------------------------------------------------------------------
    */

    'register' => [
        'title' => 'Create Account',
        'heading' => 'Create your buyer account',
        'subheading' => 'Register to start requesting parts.',
        'name_label' => 'Your name',
        'name_placeholder' => 'e.g. Jane Smith',
        'email_label' => 'Email',
        'email_placeholder' => 'you@example.com',
        'password_label' => 'Password',
        'confirm_password_label' => 'Confirm password',
        'company_name_label' => 'Company name',
        'company_name_placeholder' => 'e.g. Acme Imports Ltd',
        'phone_label' => 'Phone',
        'phone_placeholder' => 'e.g. +61 4 1234 5678',
        'country_label' => 'Default destination country',
        'country_placeholder_option' => '-- Select a country --',
        'country_help' => 'Where parts you order will usually ship to -- can be changed per request later.',
        'default_yard_label' => 'Default yard',
        'default_yard_placeholder' => 'e.g. North Island Yard',
        'default_yard_help' => 'Your usual receiving yard or warehouse -- can be changed per request later.',
        'submit' => 'Create account',
        'already_have_account' => 'Already have an account?',
        'login_link' => 'Log in',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI copy — email verification banner
    |--------------------------------------------------------------------------
    */

    'verification' => [
        'banner' => 'Please verify your email address before you can create or respond to requests.',
        'resend_button' => 'Resend verification email',
        'sent' => 'A new verification link has been sent to your email address.',
        'verified' => 'Your email address has been verified.',
        'already_verified' => 'Your email address is already verified.',
    ],

];
