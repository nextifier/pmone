<?php

use App\Models\ApiConsumer;
use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\ContactFormSubmission;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventProduct;
use App\Models\EventProductCategory;
use App\Models\Form;
use App\Models\Hotel;
use App\Models\HotelEventAllotment;
use App\Models\LinkPage;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\ShortLink;
use App\Models\Task;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Tags\Tag;

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
            'model' => User::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'roles' => [
            'label' => 'Role Management',
            'description' => 'Manage roles',
            'model' => Role::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'permissions' => [
            'label' => 'Permission Management',
            'description' => 'Manage permissions',
            'model' => Permission::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'posts' => [
            'label' => 'Posts',
            'description' => 'Manage blog posts',
            'model' => Post::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'projects' => [
            'label' => 'Projects',
            'description' => 'Manage projects',
            'model' => Project::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'short_links' => [
            'label' => 'Short Links',
            'description' => 'Manage short links',
            'model' => ShortLink::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'link_pages' => [
            'label' => 'Link Pages',
            'description' => 'Manage link pages',
            'model' => LinkPage::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'tags' => [
            'label' => 'Tags',
            'description' => 'Manage blog tags',
            'model' => Tag::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'contact_forms' => [
            'label' => 'Contact Form Submissions',
            'description' => 'Manage contact form submissions (inbox)',
            'model' => ContactFormSubmission::class,
            'actions' => ['read', 'update', 'delete'], // No create, submissions come from public
        ],
        'events' => [
            'label' => 'Events',
            'description' => 'Manage project events',
            'model' => Event::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'brands' => [
            'label' => 'Brands',
            'description' => 'Manage brands and exhibitors',
            'model' => Brand::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'partners' => [
            'label' => 'Partners',
            'description' => 'Manage event partners and sponsors',
            'model' => Partner::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'tasks' => [
            'label' => 'Tasks',
            'description' => 'Manage tasks',
            'model' => Task::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'api_consumers' => [
            'label' => 'API Consumers',
            'description' => 'Manage API consumers and keys',
            'model' => ApiConsumer::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'event_products' => [
            'label' => 'Event Products',
            'description' => 'Manage event product catalog',
            'model' => EventProduct::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'orders' => [
            'label' => 'Orders',
            'description' => 'Manage exhibitor orders',
            'model' => Order::class,
            'actions' => ['read', 'update'],
        ],
        'event_product_categories' => [
            'label' => 'Event Product Categories',
            'description' => 'Manage event product categories',
            'model' => EventProductCategory::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'event_documents' => [
            'label' => 'Event Documents',
            'description' => 'Manage event documents and rules',
            'model' => EventDocument::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'forms' => [
            'label' => 'Forms',
            'description' => 'Manage form builder and responses',
            'model' => Form::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'contacts' => [
            'label' => 'Contacts',
            'description' => 'Manage contact list',
            'model' => Contact::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'hotels' => [
            'label' => 'Hotels',
            'description' => 'Manage hotel partners and room inventory',
            'model' => Hotel::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'room_types' => [
            'label' => 'Room Types',
            'description' => 'Manage hotel room types',
            'model' => RoomType::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'allotments' => [
            'label' => 'Hotel Allotments',
            'description' => 'Manage hotel room allotments per event',
            'model' => HotelEventAllotment::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'reservations' => [
            'label' => 'Hotel Reservations',
            'description' => 'Manage hotel reservations',
            'model' => Reservation::class,
            'actions' => ['create', 'read', 'update', 'delete'],
        ],
        'app_settings' => [
            'label' => 'Application Settings',
            'description' => 'Manage global application settings (branding, etc.)',
            'model' => AppSetting::class,
            'actions' => ['read', 'update'],
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
        'hotel_reservations' => [
            'label' => 'Hotel Reservations Operations',
            'description' => 'Reservation workflow actions (voucher, cancel, refund, export, documents)',
            'permissions' => [
                'reservations.upload_voucher' => 'Upload voucher file from hotel partner',
                'reservations.send_voucher' => 'Send voucher email to guest',
                'reservations.cancel' => 'Cancel reservation',
                'reservations.refund' => 'Process refund',
                'reservations.manual_entry' => 'Create reservation manually from admin',
                'reservations.export' => 'Export reservations to Excel',
                'reservations.view_documents' => 'Download invoice and receipt PDFs',
            ],
        ],
        'event_branding' => [
            'label' => 'Event Branding',
            'description' => 'Override branding per event (used in invoices and receipts)',
            'permissions' => [
                'events.update_branding' => 'Update event branding settings',
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
