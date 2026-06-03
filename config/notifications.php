<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Recipients Configuration
    |--------------------------------------------------------------------------
    |
    | Configure notification recipients for each module/action.
    | You can specify recipients by role_id, developer_id, or 'all' for all users.
    |
    */

    'recipients' => [
        'client' => [
            'created' => [
                'roles' => [
                    '147c8a8e-52dc-4a79-a8ce-acb612b6e484',
                ],
                'developers' => [
                    '63358944-52e5-4844-8e0f-b7687719926c',
                ],
                'all' => false,
            ],
            'updated' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
            'deleted' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
        ],

        'subscription' => [
            'created' => [
                'roles' => [
                    '147c8a8e-52dc-4a79-a8ce-acb612b6e484',
                ],
                'developers' => [
                    '63358944-52e5-4844-8e0f-b7687719926c',
                ],
                'all' => false,
            ],
            'updated' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
            'deleted' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
        ],

        'payment' => [
            'created' => [
                'roles' => [
                    '147c8a8e-52dc-4a79-a8ce-acb612b6e484',
                ],
                'developers' => [
                    '63358944-52e5-4844-8e0f-b7687719926c',
                ],
                'all' => false,
            ],
            'updated' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
            'deleted' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
        ],

        'reimbursement' => [
            'created' => [
                'roles' => [
                    '147c8a8e-52dc-4a79-a8ce-acb612b6e484',
                ],
                'developers' => [
                    '63358944-52e5-4844-8e0f-b7687719926c',
                ],
                'all' => false,
            ],
            'updated' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
            'deleted' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
        ],

        'mom_meeting' => [
            'created' => [
                'roles' => [
                    '147c8a8e-52dc-4a79-a8ce-acb612b6e484',
                ],
                'developers' => [
                    '63358944-52e5-4844-8e0f-b7687719926c',
                ],
                'all' => false,
            ],
            'updated' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
            'deleted' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
        ],

        'repository' => [
            'created' => [
                'roles' => [],
                'developers' => [],
                'all' => true,
            ],
            'updated' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
            'deleted' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
        ],

        'task' => [
            'created' => [
                'roles' => [
                    '147c8a8e-52dc-4a79-a8ce-acb612b6e484',
                ],
                'developers' => [
                    '63358944-52e5-4844-8e0f-b7687719926c',
                    '2b2c0992-2671-447a-b2b2-74441976c61c',
                    '561397f1-3114-451c-8932-767b1002a127',
                    '824894a3-1f66-417b-9755-20667314745a',
                    'e920e12a-7f02-4960-a416-a67356445064',
                    '838509a8-7468-4e1c-930b-29a600f44312',
                ],
                'all' => false,
            ],
            'updated' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
            'deleted' => [
                'roles' => [],
                'developers' => [],
                'all' => false,
            ],
        ],

    ],
];
