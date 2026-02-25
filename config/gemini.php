<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    |
    | Your Google Gemini API key from https://aistudio.google.com/apikey
    |
    */
    'api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The Gemini model used for all AI requests.
    | Options: gemini-2.0-flash, gemini-3.0-flash, gemini-3-pro-preview
    |
    */
    'default_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

];
