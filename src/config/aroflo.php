<?php

return [

    'apiurl' => env('AROFLO_API_URL'),
    'apiauth' => [
        'uEncoded' => env('AROFLO_API_U_ENCODED'),
        'pEncoded' => env('AROFLO_API_P_ENCODED'),
        'secret' => env('AROFLO_API_SECRET_KEY'),
        'orgEncoded' => env('AROFLO_API_ORG_ENCODED')
    ],
    'cogs' => [
        'to' => env('COGS_TO'),
        'cc' => env('COGS_CC')
    ],
    'imap' => [
        'inbox' => env('IMAP_INBOX'),
        'username' => env('IMAP_USERNAME'),
        'password' =>env('IMAP_PASSWORD')
    ],
    'delay' => 5
];
