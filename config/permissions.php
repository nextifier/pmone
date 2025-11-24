<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permission Resources
    |--------------------------------------------------------------------------
    |
    | Define all models/resources that should have CRUD permissions generated.
    | The key is the resource identifier, and the value contains configuration.
    |
    | For each resource, the following permissions will be created:
    | - {resource}.create
    | - {resource}.read
    | - {resource}.update
    | - {resource}.delete
    |
    */
    'resources' => [
        'users' => [
            'label' => 'User Management',
            'description' => 'Manage users in the system',
            'model' => \App\Models\User::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'roles' => [
            'label' => 'Role Management',
            'description' => 'Manage roles and permissions',
            'model' => \Spatie\Permission\Models\Role::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        // Add more resources here as you create new models
        // Example:
        // 'posts' => [
        //     'label' => 'Posts',
        //     'description' => 'Manage blog posts',
        //     'model' => \App\Models\Post::class,
        //     'actions' => ['create', 'read', 'update', 'delete'],
        // ],
        // 'projects' => [
        //     'label' => 'Projects',
        //     'description' => 'Manage projects',
        //     'model' => \App\Models\Project::class,
        //     'actions' => ['create', 'read', 'update', 'delete'],
        // ],
        // 'short_links' => [
        //     'label' => 'Short Links',
        //     'description' => 'Manage short links',
        //     'model' => \App\Models\ShortLink::class,
        //     'actions' => ['create', 'read', 'update', 'delete'],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Define custom permissions that don't follow the CRUD pattern.
    | These permissions are grouped separately and managed individually.
    |
    */
    'custom' => [
        'admin' => [
            'label' => 'Administration',
            'description' => 'System administration permissions',
            'permissions' => [
                'admin.view' => 'Access admin dashboard',
                'admin.settings' => 'Manage system settings',
                'admin.logs' => 'View system logs',
                'admin.maintenance' => 'Put system in maintenance mode',
            ],
        ],
        // Add more custom permission groups here
        // Example:
        // 'reports' => [
        //     'label' => 'Reports',
        //     'description' => 'Report generation and viewing',
        //     'permissions' => [
        //         'reports.view' => 'View reports',
        //         'reports.export' => 'Export reports',
        //         'reports.schedule' => 'Schedule report generation',
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Permissions Action Labels
    |--------------------------------------------------------------------------
    |
    | Define human-readable labels for standard CRUD actions.
    |
    */
    'action_labels' => [
        'create' => 'Create',
        'read' => 'View/Read',
        'update' => 'Update/Edit',
        'delete' => 'Delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-sync on Model Creation
    |--------------------------------------------------------------------------
    |
    | When set to true, permissions will be automatically synced when running
    | migrations or seeders. Set to false if you prefer manual sync via command.
    |
    */
    'auto_sync' => env('PERMISSIONS_AUTO_SYNC', true),
];
