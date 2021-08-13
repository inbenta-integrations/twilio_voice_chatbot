<?php

/**
 * DON'T MODIFY THIS FILE!!! READ "conf/README.md" BEFORE.
 */

// Inbenta Hyperchat configuration
return [
    'chat' => [
        'enabled' => true,
        'version' => '1',
        'appId' => '',
        'secret' => '',
        'roomId' => 1,             // Numeric value, no string (without quotes)
        'lang' => 'en',
        'source' => 9,             // Numeric value, no string (without quotes)
        'guestName' => '',
        'guestContact' => '',
        'regionServer' => 'us',
        'server' => '<server>',    // Your HyperChat server URL (ask your contact person at Inbenta)
        'server_port' => 443,
        'surveyId' => '1'
    ],
    'triesBeforeEscalation' => 2,
    'negativeRatingsBeforeEscalation' => 0
];
