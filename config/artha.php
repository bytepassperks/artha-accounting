<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Artha Business OS — suite configuration
|--------------------------------------------------------------------------
|
| The unified app launcher (waffle grid) shown in the top bar of every Artha
| app reads this list so all apps present the same suite. URLs are env-driven
| so they can be repointed to brand subdomains (accounting.arthize.com, …)
| without a code change once their DNS is live.
|
*/

return [
    // Which tile represents THIS deployment (highlighted + marked "current").
    'current' => env('ARTHA_APP_KEY', 'accounting'),

    /*
    | Shared service-to-service token. Other Artha modules (CRM "Ask Artha",
    | the Automations engine) authenticate to this app's read-only REST API
    | with this bearer token. Empty token => the API is disabled (404).
    */
    'api_token' => env('ARTHA_API_TOKEN', ''),

    'apps' => [
        [
            'key' => 'crm',
            'name' => 'CRM',
            'description' => 'People, companies & deals',
            'url' => env('ARTHA_URL_CRM', 'https://arthize.com'),
            // heroicon: users
            'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
        ],
        [
            'key' => 'accounting',
            'name' => 'Accounting',
            'description' => 'Ledger, invoices & banking',
            'url' => env('ARTHA_URL_ACCOUNTING', 'https://artha-accounting.osc-fr1.scalingo.io'),
            // heroicon: calculator
            'icon' => 'M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Zm0 2.25h.008v.008h-.008v-.008Zm0-4.5h.008v.008h-.008v-.008ZM6 6.75A.75.75 0 0 1 6.75 6h10.5a.75.75 0 0 1 .75.75v3a.75.75 0 0 1-.75.75H6.75A.75.75 0 0 1 6 9.75v-3ZM6.75 3h10.5A2.25 2.25 0 0 1 19.5 5.25v13.5A2.25 2.25 0 0 1 17.25 21H6.75a2.25 2.25 0 0 1-2.25-2.25V5.25A2.25 2.25 0 0 1 6.75 3Z',
        ],
        [
            'key' => 'projects',
            'name' => 'Projects',
            'description' => 'Tasks & Kanban boards',
            'url' => env('ARTHA_URL_PROJECTS', 'https://arthize.com'),
            // heroicon: rectangle-stack
            'icon' => 'M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122',
        ],
        [
            'key' => 'automations',
            'name' => 'Automations',
            'description' => 'Workflows across the suite',
            'url' => env('ARTHA_URL_AUTOMATIONS', 'https://artha-automations.osc-fr1.scalingo.io'),
            // heroicon: bolt
            'icon' => 'm3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
        ],
    ],
];
