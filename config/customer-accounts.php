<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Automatically Created Customer Accounts
    |--------------------------------------------------------------------------
    |
    | Public forms create a customer account when no account exists for the
    | submitted email. Each account receives its own generated password.
    |
    */
    'temporary_password_length' => (int) env('CUSTOMER_TEMP_PASSWORD_LENGTH', 16),
];
