<?php

declare(strict_types=1);

return [
    'adminEmail'                    => 'admin@example.com',
    'supportEmail'                  => 'support@example.com',
    'senderEmail'                   => 'noreply@example.com',
    'senderName'                    => 'Example.com mailer',
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength'        => 8,
    'dingtalk'                      => [
        'appKey'            => 'dingyertk2al3yctuejl',
        'appSecret'         => 'L6-QmDpnI-_IYy9du6SGzoxxy_5qO7acaYeQzNdhlRf3YaY64GSGkmoUi_mwnG4w',
        'agentId'           => '4216270220',
        'requestIntervalMs' => 300,
        'maxRetries'        => 3,
    ],
];
