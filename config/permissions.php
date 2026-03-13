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
            'description' => 'Manage roles',
            'model' => \Spatie\Permission\Models\Role::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'permissions' => [
            'label' => 'Permission Management',
            'description' => 'Manage permissions',
            'model' => \Spatie\Permission\Models\Permission::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'posts' => [
            'label' => 'Posts',
            'description' => 'Manage blog posts',
            'model' => \App\Models\Post::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'projects' => [
            'label' => 'Projects',
            'description' => 'Manage projects',
            'model' => \App\Models\Project::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'short_links' => [
            'label' => 'Short Links',
            'description' => 'Manage short links',
            'model' => \App\Models\ShortLink::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'tags' => [
            'label' => 'Tags',
            'description' => 'Manage blog tags',
            'model' => \Spatie\Tags\Tag::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'contact_forms' => [
            'label' => 'Contact Form Submissions',
            'description' => 'Manage contact form submissions (inbox)',
            'model' => \App\Models\ContactFormSubmission::class,
            'actions' => ['read', 'update', 'delete'], // No create, submissions come from public
        ],
        'events' => [
            'label' => 'Events',
            'description' => 'Manage project events',
            'model' => \App\Models\Event::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'brands' => [
            'label' => 'Brands',
            'description' => 'Manage brands and exhibitors',
            'model' => \App\Models\Brand::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'tasks' => [
            'label' => 'Tasks',
            'description' => 'Manage tasks',
            'model' => \App\Models\Task::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'api_consumers' => [
            'label' => 'API Consumers',
            'description' => 'Manage API consumers and keys',
            'model' => \App\Models\ApiConsumer::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'event_products' => [
            'label' => 'Event Products',
            'description' => 'Manage event product catalog',
            'model' => \App\Models\EventProduct::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'orders' => [
            'label' => 'Orders',
            'description' => 'Manage exhibitor orders',
            'model' => \App\Models\Order::class,
            'actions' => ['read', 'update'],
        ],
        'event_product_categories' => [
            'label' => 'Event Product Categories',
            'description' => 'Manage event product categories',
            'model' => \App\Models\EventProductCategory::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'event_documents' => [
            'label' => 'Event Documents',
            'description' => 'Manage event documents and rules',
            'model' => \App\Models\EventDocument::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'forms' => [
            'label' => 'Forms',
            'description' => 'Manage form builder and responses',
            'model' => \App\Models\Form::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'contacts' => [
            'label' => 'Contacts',
            'description' => 'Manage contact list',
            'model' => \App\Models\Contact::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
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
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'View and manage analytics data',
            'permissions' => [
                'analytics.view' => 'View analytics data',
                'analytics.export' => 'Export analytics data',
            ],
        ],
        'staff_roles' => [
            'label' => 'Staff Sub-roles',
            'description' => 'Staff department-level permissions',
            'permissions' => [
                'operational' => 'Operational department access',
                'project-coordinator' => 'Project coordinator access',
                'finance' => 'Finance department access',
            ],
        ],
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
