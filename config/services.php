<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    'wandbox' => [
        'base_url' => env('WANDBOX_BASE_URL', 'https://wandbox.org/api'),
        'timeout' => (int) env('WANDBOX_TIMEOUT', 20),
        // Keys must mirror App\Models\Question::SUPPORTED_LANGUAGES.
        'compilers' => [
            'python' => env('WANDBOX_COMPILER_PYTHON', 'cpython-3.12.7'),
            'php' => env('WANDBOX_COMPILER_PHP', 'php-8.3.12'),
            'javascript' => env('WANDBOX_COMPILER_JAVASCRIPT', 'nodejs-20.17.0'),
            'cpp' => env('WANDBOX_COMPILER_CPP', 'gcc-13.2.0'),
            'java' => env('WANDBOX_COMPILER_JAVA', 'openjdk-jdk-21+35'),
            'sql' => env('WANDBOX_COMPILER_SQL', 'sqlite-3.46.1'),
        ],
    ],

];
