<?php return array (
  'app' => 
  array (
    'name' => 'Laravel',
    'env' => 'local',
    'debug' => true,
    'url' => 'http://localhost',
    'asset_url' => NULL,
    'timezone' => 'Africa/Cairo',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => 'base64:eJo7KiNZA/R0KWvWMpmNMJMoPbRe6Xhlq4Dycw5hy9Y=',
    'cipher' => 'AES-256-CBC',
    'maintenance' => 
    array (
      'driver' => 'file',
    ),
    'providers' => 
    array (
      0 => 'Illuminate\\Auth\\AuthServiceProvider',
      1 => 'Illuminate\\Broadcasting\\BroadcastServiceProvider',
      2 => 'Illuminate\\Bus\\BusServiceProvider',
      3 => 'Illuminate\\Cache\\CacheServiceProvider',
      4 => 'Illuminate\\Foundation\\Providers\\ConsoleSupportServiceProvider',
      5 => 'Illuminate\\Cookie\\CookieServiceProvider',
      6 => 'Illuminate\\Database\\DatabaseServiceProvider',
      7 => 'Illuminate\\Encryption\\EncryptionServiceProvider',
      8 => 'Illuminate\\Filesystem\\FilesystemServiceProvider',
      9 => 'Illuminate\\Foundation\\Providers\\FoundationServiceProvider',
      10 => 'Illuminate\\Hashing\\HashServiceProvider',
      11 => 'Illuminate\\Mail\\MailServiceProvider',
      12 => 'Illuminate\\Notifications\\NotificationServiceProvider',
      13 => 'Illuminate\\Pagination\\PaginationServiceProvider',
      14 => 'Illuminate\\Pipeline\\PipelineServiceProvider',
      15 => 'Illuminate\\Queue\\QueueServiceProvider',
      16 => 'Illuminate\\Redis\\RedisServiceProvider',
      17 => 'Illuminate\\Auth\\Passwords\\PasswordResetServiceProvider',
      18 => 'Illuminate\\Session\\SessionServiceProvider',
      19 => 'Illuminate\\Translation\\TranslationServiceProvider',
      20 => 'Illuminate\\Validation\\ValidationServiceProvider',
      21 => 'Illuminate\\View\\ViewServiceProvider',
      22 => 'Spatie\\Permission\\PermissionServiceProvider',
      23 => 'App\\Providers\\AppServiceProvider',
      24 => 'App\\Providers\\AuthServiceProvider',
      25 => 'App\\Providers\\BroadcastServiceProvider',
      26 => 'App\\Providers\\EventServiceProvider',
      27 => 'App\\Providers\\RouteServiceProvider',
    ),
    'aliases' => 
    array (
      'App' => 'Illuminate\\Support\\Facades\\App',
      'Arr' => 'Illuminate\\Support\\Arr',
      'Artisan' => 'Illuminate\\Support\\Facades\\Artisan',
      'Auth' => 'Illuminate\\Support\\Facades\\Auth',
      'Blade' => 'Illuminate\\Support\\Facades\\Blade',
      'Broadcast' => 'Illuminate\\Support\\Facades\\Broadcast',
      'Bus' => 'Illuminate\\Support\\Facades\\Bus',
      'Cache' => 'Illuminate\\Support\\Facades\\Cache',
      'Config' => 'Illuminate\\Support\\Facades\\Config',
      'Cookie' => 'Illuminate\\Support\\Facades\\Cookie',
      'Crypt' => 'Illuminate\\Support\\Facades\\Crypt',
      'Date' => 'Illuminate\\Support\\Facades\\Date',
      'DB' => 'Illuminate\\Support\\Facades\\DB',
      'Eloquent' => 'Illuminate\\Database\\Eloquent\\Model',
      'Event' => 'Illuminate\\Support\\Facades\\Event',
      'File' => 'Illuminate\\Support\\Facades\\File',
      'Gate' => 'Illuminate\\Support\\Facades\\Gate',
      'Hash' => 'Illuminate\\Support\\Facades\\Hash',
      'Http' => 'Illuminate\\Support\\Facades\\Http',
      'Js' => 'Illuminate\\Support\\Js',
      'Lang' => 'Illuminate\\Support\\Facades\\Lang',
      'Log' => 'Illuminate\\Support\\Facades\\Log',
      'Mail' => 'Illuminate\\Support\\Facades\\Mail',
      'Notification' => 'Illuminate\\Support\\Facades\\Notification',
      'Password' => 'Illuminate\\Support\\Facades\\Password',
      'Queue' => 'Illuminate\\Support\\Facades\\Queue',
      'RateLimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
      'Redirect' => 'Illuminate\\Support\\Facades\\Redirect',
      'Request' => 'Illuminate\\Support\\Facades\\Request',
      'Response' => 'Illuminate\\Support\\Facades\\Response',
      'Route' => 'Illuminate\\Support\\Facades\\Route',
      'Schema' => 'Illuminate\\Support\\Facades\\Schema',
      'Session' => 'Illuminate\\Support\\Facades\\Session',
      'Storage' => 'Illuminate\\Support\\Facades\\Storage',
      'Str' => 'Illuminate\\Support\\Str',
      'URL' => 'Illuminate\\Support\\Facades\\URL',
      'Validator' => 'Illuminate\\Support\\Facades\\Validator',
      'View' => 'Illuminate\\Support\\Facades\\View',
      'Vite' => 'Illuminate\\Support\\Facades\\Vite',
    ),
  ),
  'auth' => 
  array (
    'defaults' => 
    array (
      'guard' => 'api',
      'passwords' => 'users',
    ),
    'guards' => 
    array (
      'api' => 
      array (
        'driver' => 'jwt',
        'provider' => 'users',
        'expire' => 6000,
      ),
      'sanctum' => 
      array (
        'driver' => 'sanctum',
        'provider' => NULL,
      ),
    ),
    'providers' => 
    array (
      'users' => 
      array (
        'driver' => 'eloquent',
        'model' => 'App\\Models\\User',
      ),
    ),
    'passwords' => 
    array (
      'users' => 
      array (
        'provider' => 'users',
        'table' => 'password_resets',
        'expire' => 6000,
        'throttle' => 60,
      ),
    ),
    'password_timeout' => 10800,
  ),
  'broadcasting' => 
  array (
    'default' => 'pusher',
    'connections' => 
    array (
      'pusher' => 
      array (
        'driver' => 'pusher',
        'key' => 'laravelWebSocketKey',
        'secret' => 'laravelWebSocketSecret',
        'app_id' => 'laravelWebSocketID',
        'options' => 
        array (
          'cluster' => 'mt1',
          'host' => '127.0.0.1',
          'port' => 6001,
          'scheme' => 'http',
        ),
        'client_options' => 
        array (
        ),
      ),
      'ably' => 
      array (
        'driver' => 'ably',
        'key' => NULL,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
      ),
      'log' => 
      array (
        'driver' => 'log',
      ),
      'null' => 
      array (
        'driver' => 'null',
      ),
    ),
  ),
  'cache' => 
  array (
    'default' => 'file',
    'stores' => 
    array (
      'apc' => 
      array (
        'driver' => 'apc',
      ),
      'array' => 
      array (
        'driver' => 'array',
        'serialize' => false,
      ),
      'database' => 
      array (
        'driver' => 'database',
        'table' => 'cache',
        'connection' => NULL,
        'lock_connection' => NULL,
      ),
      'file' => 
      array (
        'driver' => 'file',
        'path' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\framework/cache/data',
      ),
      'memcached' => 
      array (
        'driver' => 'memcached',
        'persistent_id' => NULL,
        'sasl' => 
        array (
          0 => NULL,
          1 => NULL,
        ),
        'options' => 
        array (
        ),
        'servers' => 
        array (
          0 => 
          array (
            'host' => '127.0.0.1',
            'port' => 11211,
            'weight' => 100,
          ),
        ),
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
      ),
      'dynamodb' => 
      array (
        'driver' => 'dynamodb',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'table' => 'cache',
        'endpoint' => NULL,
      ),
      'octane' => 
      array (
        'driver' => 'octane',
      ),
    ),
    'prefix' => 'laravel_cache_',
  ),
  'cors' => 
  array (
    'paths' => 
    array (
      0 => 'api/*',
      1 => 'sanctum/csrf-cookie',
    ),
    'allowed_methods' => 
    array (
      0 => '*',
    ),
    'allowed_origins' => 
    array (
      0 => 'https://mag-opt.com',
      1 => 'https://www.mag-opt.com',
      2 => 'http://localhost:4200',
      3 => 'http://127.0.0.1:4200',
    ),
    'allowed_origins_patterns' => 
    array (
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
    ),
    'max_age' => 0,
    'supports_credentials' => false,
  ),
  'database' => 
  array (
    'default' => 'mysql',
    'connections' => 
    array (
      'sqlite' => 
      array (
        'driver' => 'sqlite',
        'url' => NULL,
        'database' => 'newerp',
        'prefix' => '',
        'foreign_key_constraints' => true,
      ),
      'mysql' => 
      array (
        'driver' => 'mysql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'newerp',
        'username' => 'root',
        'password' => '',
        'unix_socket' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => NULL,
        'options' => 
        array (
        ),
      ),
      'pgsql' => 
      array (
        'driver' => 'pgsql',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'newerp',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
      ),
      'sqlsrv' => 
      array (
        'driver' => 'sqlsrv',
        'url' => NULL,
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'newerp',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
      ),
    ),
    'migrations' => 'migrations',
    'redis' => 
    array (
      'client' => 'phpredis',
      'options' => 
      array (
        'cluster' => 'redis',
        'prefix' => 'laravel_database_',
      ),
      'default' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '0',
      ),
      'cache' => 
      array (
        'url' => NULL,
        'host' => '127.0.0.1',
        'username' => NULL,
        'password' => NULL,
        'port' => '6379',
        'database' => '1',
      ),
    ),
  ),
  'filesystems' => 
  array (
    'default' => 'local',
    'disks' => 
    array (
      'local' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\app',
        'throw' => false,
      ),
      'public' => 
      array (
        'driver' => 'local',
        'root' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\app/public',
        'url' => 'http://localhost/storage',
        'visibility' => 'public',
        'throw' => false,
      ),
      's3' => 
      array (
        'driver' => 's3',
        'key' => '',
        'secret' => '',
        'region' => 'us-east-1',
        'bucket' => '',
        'url' => NULL,
        'endpoint' => NULL,
        'use_path_style_endpoint' => false,
        'throw' => false,
      ),
    ),
    'links' => 
    array (
      'C:\\xampp\\htdocs\\Erp_new\\backend\\public\\storage' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\app/public',
    ),
  ),
  'hashing' => 
  array (
    'driver' => 'bcrypt',
    'bcrypt' => 
    array (
      'rounds' => 10,
    ),
    'argon' => 
    array (
      'memory' => 65536,
      'threads' => 1,
      'time' => 4,
    ),
  ),
  'items_import' => 
  array (
    'new_item' => 
    array (
      'production_id' => NULL,
      'measurement_id' => NULL,
      'warehouse' => 'مخزن مواد خام',
      'category_price' => 0.0,
      'initial_balance' => 0.0,
      'minimum_quantity' => 0.0,
      'category_image' => '',
    ),
    'item_code_prefix' => 'ITM-',
  ),
  'jwt' => 
  array (
    'secret' => 'Dw32rsnKml0D0pNs2rhUmwXtH4t6nNeDb23uj521mpNCk9FImKj4hr71m7Lj6oPl',
    'keys' => 
    array (
      'public' => NULL,
      'private' => NULL,
      'passphrase' => NULL,
    ),
    'ttl' => 6000,
    'refresh_ttl' => 20160,
    'algo' => 'HS256',
    'required_claims' => 
    array (
      0 => 'iss',
      1 => 'iat',
      2 => 'exp',
      3 => 'nbf',
      4 => 'sub',
      5 => 'jti',
    ),
    'persistent_claims' => 
    array (
    ),
    'lock_subject' => true,
    'leeway' => 0,
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 0,
    'decrypt_cookies' => false,
    'providers' => 
    array (
      'jwt' => 'Tymon\\JWTAuth\\Providers\\JWT\\Lcobucci',
      'auth' => 'Tymon\\JWTAuth\\Providers\\Auth\\Illuminate',
      'storage' => 'Tymon\\JWTAuth\\Providers\\Storage\\Illuminate',
    ),
  ),
  'logging' => 
  array (
    'default' => 'stack',
    'deprecations' => 
    array (
      'channel' => NULL,
      'trace' => false,
    ),
    'channels' => 
    array (
      'stack' => 
      array (
        'driver' => 'stack',
        'channels' => 
        array (
          0 => 'single',
        ),
        'ignore_exceptions' => false,
      ),
      'single' => 
      array (
        'driver' => 'single',
        'path' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\logs/laravel.log',
        'level' => 'debug',
      ),
      'daily' => 
      array (
        'driver' => 'daily',
        'path' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\logs/laravel.log',
        'level' => 'debug',
        'days' => 14,
      ),
      'slack' => 
      array (
        'driver' => 'slack',
        'url' => NULL,
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'debug',
      ),
      'papertrail' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\SyslogUdpHandler',
        'handler_with' => 
        array (
          'host' => NULL,
          'port' => NULL,
          'connectionString' => 'tls://:',
        ),
      ),
      'stderr' => 
      array (
        'driver' => 'monolog',
        'level' => 'debug',
        'handler' => 'Monolog\\Handler\\StreamHandler',
        'formatter' => NULL,
        'with' => 
        array (
          'stream' => 'php://stderr',
        ),
      ),
      'syslog' => 
      array (
        'driver' => 'syslog',
        'level' => 'debug',
      ),
      'errorlog' => 
      array (
        'driver' => 'errorlog',
        'level' => 'debug',
      ),
      'null' => 
      array (
        'driver' => 'monolog',
        'handler' => 'Monolog\\Handler\\NullHandler',
      ),
      'emergency' => 
      array (
        'path' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\logs/laravel.log',
      ),
    ),
  ),
  'mail' => 
  array (
    'default' => 'smtp',
    'mailers' => 
    array (
      'smtp' => 
      array (
        'transport' => 'smtp',
        'host' => 'mailpit',
        'port' => '1025',
        'encryption' => NULL,
        'username' => NULL,
        'password' => NULL,
        'timeout' => NULL,
        'local_domain' => NULL,
      ),
      'ses' => 
      array (
        'transport' => 'ses',
      ),
      'mailgun' => 
      array (
        'transport' => 'mailgun',
      ),
      'postmark' => 
      array (
        'transport' => 'postmark',
      ),
      'sendmail' => 
      array (
        'transport' => 'sendmail',
        'path' => '/usr/sbin/sendmail -bs -i',
      ),
      'log' => 
      array (
        'transport' => 'log',
        'channel' => NULL,
      ),
      'array' => 
      array (
        'transport' => 'array',
      ),
      'failover' => 
      array (
        'transport' => 'failover',
        'mailers' => 
        array (
          0 => 'smtp',
          1 => 'log',
        ),
      ),
    ),
    'from' => 
    array (
      'address' => 'hello@example.com',
      'name' => 'Laravel',
    ),
    'markdown' => 
    array (
      'theme' => 'default',
      'paths' => 
      array (
        0 => 'C:\\xampp\\htdocs\\Erp_new\\backend\\resources\\views/vendor/mail',
      ),
    ),
  ),
  'order_rbac_profiles' => 
  array (
    'ship_collect' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Operation Specialist',
        3 => 'Logistics Specialist',
      ),
      'permissions' => 
      array (
        0 => 'orders.change_status',
        1 => 'orders.assign_driver',
        2 => 'orders.view',
      ),
    ),
    'part_shipment' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
      ),
      'permissions' => 
      array (
        0 => 'orders.edit',
        1 => 'orders.change_status',
        2 => 'orders.view',
      ),
    ),
    'orders_create' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Data Entry',
      ),
      'permissions' => 
      array (
        0 => 'orders.create',
        1 => 'orders.view',
      ),
    ),
    'companies_main' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Account Management',
        3 => 'Logistics Specialist',
        4 => 'Financial Accounts',
        5 => 'Data Entry',
      ),
      'permissions' => 
      array (
        0 => 'orders.view',
        1 => 'finance.edit',
        2 => 'customer_companies.view',
        3 => 'customer_companies.manage',
      ),
    ),
    'companies_balance' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Account Management',
        3 => 'Logistics Specialist',
        4 => 'Financial Accounts',
      ),
      'permissions' => 
      array (
        0 => 'orders.view',
        1 => 'finance.view',
        2 => 'finance.edit',
        3 => 'customer_companies.view',
        4 => 'customer_companies.manage',
        5 => 'customer_companies.statement',
        6 => 'customer_companies.collect',
      ),
    ),
    'edit_order' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Shipping Management',
      ),
      'permissions' => 
      array (
        0 => 'orders.edit',
        1 => 'orders.view',
      ),
    ),
    'confirm_order' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Shipping Management',
        3 => 'Customer Service',
      ),
      'permissions' => 
      array (
        0 => 'orders.change_status',
        1 => 'orders.view',
      ),
    ),
    'refuse_maintain' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Operation Specialist',
        3 => 'Logistics Specialist',
        4 => 'Shipping Management',
      ),
      'permissions' => 
      array (
        0 => 'orders.change_status',
        1 => 'orders.view',
      ),
    ),
    'postpone_order' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Operation Specialist',
        3 => 'Logistics Specialist',
        4 => 'Shipping Management',
      ),
      'permissions' => 
      array (
        0 => 'orders.change_status',
      ),
    ),
    'shipping_company_crud' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Operation Specialist',
        3 => 'Account Management',
        4 => 'Logistics Specialist',
        5 => 'Shipping Management',
      ),
      'permissions' => 
      array (
        0 => 'nav.shipping.master',
        1 => 'orders.view',
        2 => 'system.rbac',
      ),
    ),
    'shipping_company_manage' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Account Management',
        3 => 'Logistics Specialist',
        4 => 'Shipping Management',
      ),
      'permissions' => 
      array (
        0 => 'shipping.companies.manage',
        1 => 'collection_companies.manage',
        2 => 'nav.shipping.master',
        3 => 'system.rbac',
      ),
    ),
    'collection_settlements' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Financial Accounts',
        2 => 'Account Management',
        3 => 'Logistics Specialist',
      ),
      'permissions' => 
      array (
        0 => 'settlements.manage',
        1 => 'finance.view',
        2 => 'nav.shipping.accounts_report',
        3 => 'system.rbac',
      ),
    ),
    'shipping_company_statement' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Operation Specialist',
        3 => 'Account Management',
        4 => 'Logistics Specialist',
        5 => 'Shipping Management',
        6 => 'Financial Accounts',
      ),
      'permissions' => 
      array (
        0 => 'shipping.companies.statement',
        1 => 'nav.shipping.master',
        2 => 'orders.view',
        3 => 'system.rbac',
      ),
    ),
    'change_status' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Operation Specialist',
        3 => 'Logistics Specialist',
        4 => 'Shipping Management',
        5 => 'Data Entry',
      ),
      'permissions' => 
      array (
        0 => 'orders.change_status',
        1 => 'orders.view',
      ),
    ),
    'vip_shortage' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Data Entry',
        2 => 'Shipping Management',
        3 => 'Customer Service',
      ),
      'permissions' => 
      array (
        0 => 'orders.view',
      ),
    ),
    'offer_crud' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Data Entry',
        2 => 'Shipping Management',
        3 => 'Customer Service',
        4 => 'Corparates',
      ),
      'permissions' => 
      array (
        0 => 'nav.receipts.quotes',
        1 => 'orders.view',
      ),
    ),
    'review_temp' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Data Entry',
        2 => 'Review Management',
      ),
      'permissions' => 
      array (
        0 => 'notifications.review_filter',
        1 => 'orders.view',
        2 => 'system.rbac',
      ),
    ),
    'add_note' => 
    array (
      'departments' => 
      array (
        0 => 'Admin',
        1 => 'Operation Management',
        2 => 'Operation Specialist',
        3 => 'Shipping Management',
        4 => 'Data Entry',
        5 => 'Account Management',
        6 => 'Logistics Specialist',
        7 => 'Customer Service',
      ),
      'permissions' => 
      array (
        0 => 'orders.edit',
        1 => 'orders.view',
      ),
    ),
  ),
  'permission' => 
  array (
    'models' => 
    array (
      'permission' => 'App\\Models\\Permission',
      'role' => 'App\\Models\\Role',
    ),
    'table_names' => 
    array (
      'roles' => 'roles',
      'permissions' => 'permissions',
      'model_has_permissions' => 'model_has_permissions',
      'model_has_roles' => 'model_has_roles',
      'role_has_permissions' => 'role_has_permissions',
    ),
    'column_names' => 
    array (
      'role_pivot_key' => NULL,
      'permission_pivot_key' => NULL,
      'model_morph_key' => 'model_id',
      'team_foreign_key' => 'team_id',
    ),
    'register_permission_check_method' => false,
    'teams' => false,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,
    'cache' => 
    array (
      'expiration_time' => 
      \DateInterval::__set_state(array(
         'from_string' => true,
         'date_string' => '24 hours',
      )),
      'key' => 'spatie.permission.cache',
      'store' => 'default',
    ),
  ),
  'queue' => 
  array (
    'default' => 'sync',
    'connections' => 
    array (
      'sync' => 
      array (
        'driver' => 'sync',
      ),
      'database' => 
      array (
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
      ),
      'beanstalkd' => 
      array (
        'driver' => 'beanstalkd',
        'host' => 'localhost',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 0,
        'after_commit' => false,
      ),
      'sqs' => 
      array (
        'driver' => 'sqs',
        'key' => '',
        'secret' => '',
        'prefix' => 'https://sqs.us-east-1.amazonaws.com/your-account-id',
        'queue' => 'default',
        'suffix' => NULL,
        'region' => 'us-east-1',
        'after_commit' => false,
      ),
      'redis' => 
      array (
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => NULL,
        'after_commit' => false,
      ),
    ),
    'failed' => 
    array (
      'driver' => 'database-uuids',
      'database' => 'mysql',
      'table' => 'failed_jobs',
    ),
  ),
  'rbac' => 
  array (
    'cache_ttl' => 3600,
    'super_admin_role_slug' => 'super-admin',
    'super_admin_emails' => 
    array (
    ),
  ),
  'sanctum' => 
  array (
    'stateful' => 
    array (
      0 => 'localhost',
      1 => 'localhost:3000',
      2 => '127.0.0.1',
      3 => '127.0.0.1:8000',
      4 => '::1',
      5 => 'localhost',
    ),
    'guard' => 
    array (
      0 => 'web',
    ),
    'expiration' => NULL,
    'token_prefix' => '',
    'middleware' => 
    array (
      'verify_csrf_token' => 'App\\Http\\Middleware\\VerifyCsrfToken',
      'encrypt_cookies' => 'App\\Http\\Middleware\\EncryptCookies',
    ),
  ),
  'services' => 
  array (
    'mailgun' => 
    array (
      'domain' => NULL,
      'secret' => NULL,
      'endpoint' => 'api.mailgun.net',
      'scheme' => 'https',
    ),
    'postmark' => 
    array (
      'token' => NULL,
    ),
    'ses' => 
    array (
      'key' => '',
      'secret' => '',
      'region' => 'us-east-1',
    ),
    'meta_whatsapp' => 
    array (
      'phone_number_id' => '992330837294579',
      'access_token' => 'EAANBfjf5ke8BQj6wDWDwZCXyTCRJuZA2osiOWXm6z7tX1J96Jrc1yVZCxZBJLVlZB8E7EFOqZCcsGQz0ckGGnPHwPQECog1KCgCMwwNyDZAKVrAgXJW7ly8vWDnMWGPrkMOTpZCLomok08VCB7mFbwTmdWPCPlWVgToATbiZBMm1ZB5CZA7vOWzMtcpGQDl9QfL',
      'verify_token' => 'K9xT2pLm8QwZ4rNs7VbY1cHd6EfG3uJk',
      'phone_number_id_2' => 'SECOND_PHONE_NUMBER_ID_HERE',
      'access_token_2' => NULL,
      'verify_token_2' => NULL,
    ),
    'shopify' => 
    array (
      'shop_domain' => 'https://magalis-egypt.myshopify.com/',
      'admin_access_token' => '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',
      'api_version' => '2024-10',
      'http_timeout' => 120,
      'webhook_secret' => '50e39e5e6366d95b1780e902be156246e47e1d9765bfe13f92444e39f2c12205',
      'allowed_shop_domains' => 
      array (
        0 => 'magalis-egypt.myshopify.com',
      ),
      'accepted_topics' => 
      array (
        0 => 'orders/create',
        1 => 'orders/updated',
        2 => 'products/create',
        3 => 'products/update',
        4 => 'inventory_levels/update',
      ),
      'default_order_source_id' => 4,
      'default_shipping_method_id' => 1,
      'fallback_category_id' => 1786,
      'default_governorate' => 'غير محدد',
      'default_address' => '-',
      'default_customer_type' => 'فرد',
      'default_customer_name' => 'عميل Shopify',
      'order_type' => 'جديد',
      'placeholder_phone' => '0000000000',
      'tracking_user_id' => 1,
      'auto_import_orders' => false,
    ),
    'shipping_partner' => 
    array (
      'api_base_url' => '',
      'api_token' => '',
      'webhook_secret' => '',
      'timeout' => 60,
    ),
  ),
  'session' => 
  array (
    'driver' => 'file',
    'lifetime' => '120',
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\framework/sessions',
    'connection' => NULL,
    'table' => 'sessions',
    'store' => NULL,
    'lottery' => 
    array (
      0 => 2,
      1 => 100,
    ),
    'cookie' => 'laravel_session',
    'path' => '/',
    'domain' => NULL,
    'secure' => NULL,
    'http_only' => true,
    'same_site' => 'lax',
  ),
  'shipping_receivable' => 
  array (
    'parent_account_setting_key' => 'shipping_receivable_parent_account_id',
    'parent_account_name' => 'شركات الشحن والمناديب',
  ),
  'shopify_collection' => 
  array (
    'gateway_map' => 
    array (
      'sympl' => 'Sympl',
      'valu' => 'valU',
      'value' => 'valU',
      'souhoola' => 'Souhoola',
      'sohoola' => 'Souhoola',
      'aman' => 'Aman',
      'contact' => 'Contact',
      'forsa' => 'Forsa',
      'halan' => 'Halan',
      'fawry' => 'Fawry',
      'instapay' => 'InstaPay',
      'meeza' => 'Meeza',
      'vodafone' => 'Vodafone Cash',
    ),
    'card_aliases' => 
    array (
      0 => 'visa',
      1 => 'mastercard',
      2 => 'master card',
      3 => 'maestro',
      4 => 'amex',
      5 => 'american express',
      6 => 'credit card',
      7 => 'debit card',
      8 => 'bankcard',
      9 => 'shopify_payments',
      10 => 'shopify payments',
    ),
    'card_company' => 'Visa',
    'ambiguous_gateway_map' => 
    array (
      'paymob' => 'Paymob',
      'accept' => 'Paymob',
      'kashier' => 'Kashier',
      'paytabs' => 'PayTabs',
      'fawaterk' => 'Fawaterak',
    ),
    'default_company' => 'Visa',
    'parent_account_setting_key' => 'collection_companies_parent_account_id',
    'parent_account_name' => 'شركات التحصيل',
  ),
  'shopify_shipping_tiers' => 
  array (
    'method_names' => 
    array (
      'small' => 'Small',
      'medium' => 'Medium',
      'large' => 'Large',
    ),
    'default_tier' => 'small',
    'keyword_rules' => 
    array (
      0 => 
      array (
        'tier' => 'small',
        'keywords' => 
        array (
          0 => 'سجاد',
          1 => 'سجاده',
          2 => 'rug',
          3 => 'beach rug',
          4 => 'beach mat',
          5 => 'mat',
          6 => 'مخدة',
          7 => 'مخده',
          8 => 'مخدات',
          9 => 'pillow',
          10 => 'cushion',
        ),
      ),
      1 => 
      array (
        'tier' => 'medium',
        'keywords' => 
        array (
          0 => 'bean bag',
          1 => 'beanbag',
          2 => 'بين باج',
          3 => 'بينbage',
          4 => 'footrest',
          5 => 'foot rest',
          6 => 'مسند القدم',
          7 => 'مسند قدم',
          8 => 'مسند',
          9 => 'ottoman',
          10 => 'pouf',
          11 => 'بوف',
        ),
      ),
      2 => 
      array (
        'tier' => 'small',
        'keywords' => 
        array (
          0 => 'شنط',
          1 => 'شنطه',
          2 => 'شنطة',
          3 => 'bag',
          4 => 'tote',
          5 => 'handbag',
          6 => 'خشب',
          7 => 'wood',
          8 => 'wooden',
          9 => 'مطبخ',
          10 => 'kitchen',
        ),
      ),
      3 => 
      array (
        'tier' => 'medium',
        'keywords' => 
        array (
          0 => 'حاجات البحر',
          1 => 'beach',
          2 => 'شاطئ',
          3 => 'sea',
        ),
      ),
      4 => 
      array (
        'tier' => 'large',
        'keywords' => 
        array (
          0 => 'كرسي',
          1 => 'كراسي',
          2 => 'chair',
          3 => 'chairs',
          4 => 'طاولة',
          5 => 'table',
          6 => 'desk',
          7 => 'أثاث',
          8 => 'اثاث',
          9 => 'furniture',
          10 => 'sofa',
          11 => 'كنبة',
          12 => 'كنبه',
          13 => 'bed',
          14 => 'سرير',
          15 => 'cabinet',
          16 => 'دولاب',
          17 => 'wardrobe',
          18 => 'خزانة',
        ),
      ),
    ),
    'warehouse_tiers' => 
    array (
      'small' => 
      array (
        0 => 'شنط',
        1 => 'bags',
        2 => 'bag',
        3 => 'أخشاب',
        4 => 'اخشاب',
        5 => 'wood',
        6 => 'مطبخ',
        7 => 'kitchen',
      ),
      'medium' => 
      array (
        0 => 'بحر',
        1 => 'beach',
        2 => 'شاطئ',
      ),
    ),
  ),
  'view' => 
  array (
    'paths' => 
    array (
      0 => 'C:\\xampp\\htdocs\\Erp_new\\backend\\resources\\views',
    ),
    'compiled' => 'C:\\xampp\\htdocs\\Erp_new\\backend\\storage\\framework\\views',
  ),
  'websockets' => 
  array (
    'dashboard' => 
    array (
      'port' => 6001,
    ),
    'apps' => 
    array (
      0 => 
      array (
        'id' => 'laravelWebSocketID',
        'name' => 'Laravel',
        'key' => 'laravelWebSocketKey',
        'secret' => 'laravelWebSocketSecret',
        'path' => NULL,
        'capacity' => NULL,
        'enable_client_messages' => false,
        'enable_statistics' => true,
      ),
    ),
    'app_provider' => 'BeyondCode\\LaravelWebSockets\\Apps\\ConfigAppProvider',
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'max_request_size_in_kb' => 250,
    'path' => 'laravel-websockets',
    'middleware' => 
    array (
      0 => 'web',
      1 => 'BeyondCode\\LaravelWebSockets\\Dashboard\\Http\\Middleware\\Authorize',
    ),
    'statistics' => 
    array (
      'model' => 'BeyondCode\\LaravelWebSockets\\Statistics\\Models\\WebSocketsStatisticsEntry',
      'logger' => 'BeyondCode\\LaravelWebSockets\\Statistics\\Logger\\HttpStatisticsLogger',
      'interval_in_seconds' => 60,
      'delete_statistics_older_than_days' => 60,
      'perform_dns_lookup' => false,
    ),
    'ssl' => 
    array (
      'local_cert' => NULL,
      'local_pk' => NULL,
      'passphrase' => NULL,
    ),
    'channel_manager' => 'BeyondCode\\LaravelWebSockets\\WebSockets\\Channels\\ChannelManagers\\ArrayChannelManager',
  ),
  'whatsapp_meta_templates' => 
  array (
    'media_base_url' => 'http://localhost',
    'default_header_image_url' => 'http://localhost/images/whatsapp-meta-default.png',
    'review_feedback_header_image_url' => 'http://localhost/images/whatsapp-meta-review-feedback-header.jpeg',
    'templates' => 
    array (
      0 => 
      array (
        'name' => 'order_confirmation_flow',
        'language' => 'ar',
        'ui_label' => 'تجهيز الطلب بالعربية',
        'header_format' => 'omit',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
          0 => 'اسم العميل',
          1 => 'رقم الطلب',
        ),
        'body_param_keys' => 
        array (
          0 => 'customer_name',
          1 => 'id',
        ),
        'phone_number_id' => NULL,
        'button_ids' => 
        array (
          0 => 'confirm_order',
          1 => 'postpone_order',
          2 => 'cancel_order',
        ),
      ),
      1 => 
      array (
        'name' => 'order_flow',
        'language' => 'en_US',
        'api_language_code' => 'en',
        'ui_label' => 'تجهيز الطلب بالإنجليزية',
        'header_format' => 'omit',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
          0 => 'اسم العميل',
          1 => 'رقم الطلب',
        ),
        'body_param_keys' => 
        array (
          0 => 'customer_name',
          1 => 'id',
        ),
        'phone_number_id' => NULL,
      ),
      2 => 
      array (
        'name' => 'confirm_order',
        'language' => 'ar',
        'ui_label' => 'تأكيد الطلب بالعربية',
        'header_format' => 'omit',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
          0 => 'اسم العميل',
          1 => 'رقم الطلب',
        ),
        'body_param_keys' => 
        array (
          0 => 'customer_name',
          1 => 'id',
        ),
        'phone_number_id' => NULL,
        'button_ids' => 
        array (
          0 => 'confirm_order',
          1 => 'postpone_order',
          2 => 'cancel_order',
        ),
      ),
      3 => 
      array (
        'name' => 'confirm_order',
        'language' => 'en_US',
        'api_language_code' => 'en',
        'ui_label' => 'تأكيد الطلب بالإنجليزية',
        'header_format' => 'omit',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
          0 => 'اسم العميل',
          1 => 'رقم الطلب',
        ),
        'body_param_keys' => 
        array (
          0 => 'customer_name',
          1 => 'id',
        ),
        'phone_number_id' => NULL,
      ),
      4 => 
      array (
        'name' => 'client_review',
        'language' => 'ar',
        'ui_label' => 'تقييم العميل بالعربية',
        'header_format' => 'image',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
          0 => 'اسم العميل',
        ),
        'body_param_keys' => 
        array (
          0 => 'customer_name',
        ),
        'phone_number_id' => NULL,
        'button_ids' => 
        array (
          0 => 'write_review',
          1 => 'write_review_ar',
        ),
      ),
      5 => 
      array (
        'name' => 'client_review',
        'language' => 'en_US',
        'api_language_code' => 'en',
        'ui_label' => 'تقييم العميل بالإنجليزية',
        'header_format' => 'image',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
          0 => 'اسم العميل',
        ),
        'body_param_keys' => 
        array (
          0 => 'customer_name',
        ),
        'phone_number_id' => NULL,
        'button_ids' => 
        array (
          0 => 'write_review',
          1 => 'write_review_en',
        ),
      ),
      6 => 
      array (
        'name' => 'feedback',
        'language' => 'ar',
        'ui_label' => 'فيد باك بالعربية',
        'header_format' => 'image',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
        ),
        'body_param_keys' => 
        array (
        ),
        'phone_number_id' => NULL,
        'button_ids' => 
        array (
          0 => 'write_review',
          1 => 'write_review_ar',
        ),
      ),
      7 => 
      array (
        'name' => 'feedback',
        'language' => 'en_US',
        'api_language_code' => 'en',
        'ui_label' => 'فيد باك بالانجليزية',
        'header_format' => 'image',
        'header_param_keys' => 
        array (
        ),
        'header_default_image_url' => NULL,
        'body_params' => 
        array (
        ),
        'body_param_keys' => 
        array (
        ),
        'phone_number_id' => NULL,
        'button_ids' => 
        array (
          0 => 'write_review',
          1 => 'write_review_en',
        ),
      ),
    ),
  ),
  'flare' => 
  array (
    'key' => NULL,
    'flare_middleware' => 
    array (
      0 => 'Spatie\\FlareClient\\FlareMiddleware\\RemoveRequestIp',
      1 => 'Spatie\\FlareClient\\FlareMiddleware\\AddGitInformation',
      2 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddNotifierName',
      3 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddEnvironmentInformation',
      4 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddExceptionInformation',
      5 => 'Spatie\\LaravelIgnition\\FlareMiddleware\\AddDumps',
      'Spatie\\LaravelIgnition\\FlareMiddleware\\AddLogs' => 
      array (
        'maximum_number_of_collected_logs' => 200,
      ),
      'Spatie\\LaravelIgnition\\FlareMiddleware\\AddQueries' => 
      array (
        'maximum_number_of_collected_queries' => 200,
        'report_query_bindings' => true,
      ),
      'Spatie\\LaravelIgnition\\FlareMiddleware\\AddJobs' => 
      array (
        'max_chained_job_reporting_depth' => 5,
      ),
      'Spatie\\FlareClient\\FlareMiddleware\\CensorRequestBodyFields' => 
      array (
        'censor_fields' => 
        array (
          0 => 'password',
          1 => 'password_confirmation',
        ),
      ),
      'Spatie\\FlareClient\\FlareMiddleware\\CensorRequestHeaders' => 
      array (
        'headers' => 
        array (
          0 => 'API-KEY',
        ),
      ),
    ),
    'send_logs_as_events' => true,
  ),
  'ignition' => 
  array (
    'editor' => 'phpstorm',
    'theme' => 'auto',
    'enable_share_button' => true,
    'register_commands' => false,
    'solution_providers' => 
    array (
      0 => 'Spatie\\Ignition\\Solutions\\SolutionProviders\\BadMethodCallSolutionProvider',
      1 => 'Spatie\\Ignition\\Solutions\\SolutionProviders\\MergeConflictSolutionProvider',
      2 => 'Spatie\\Ignition\\Solutions\\SolutionProviders\\UndefinedPropertySolutionProvider',
      3 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\IncorrectValetDbCredentialsSolutionProvider',
      4 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingAppKeySolutionProvider',
      5 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\DefaultDbNameSolutionProvider',
      6 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\TableNotFoundSolutionProvider',
      7 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingImportSolutionProvider',
      8 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\InvalidRouteActionSolutionProvider',
      9 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\ViewNotFoundSolutionProvider',
      10 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\RunningLaravelDuskInProductionProvider',
      11 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingColumnSolutionProvider',
      12 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\UnknownValidationSolutionProvider',
      13 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingMixManifestSolutionProvider',
      14 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingViteManifestSolutionProvider',
      15 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\MissingLivewireComponentSolutionProvider',
      16 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\UndefinedViewVariableSolutionProvider',
      17 => 'Spatie\\LaravelIgnition\\Solutions\\SolutionProviders\\GenericLaravelExceptionSolutionProvider',
    ),
    'ignored_solution_providers' => 
    array (
    ),
    'enable_runnable_solutions' => NULL,
    'remote_sites_path' => 'C:\\xampp\\htdocs\\Erp_new\\backend',
    'local_sites_path' => '',
    'housekeeping_endpoint_prefix' => '_ignition',
    'settings_file_path' => '',
    'recorders' => 
    array (
      0 => 'Spatie\\LaravelIgnition\\Recorders\\DumpRecorder\\DumpRecorder',
      1 => 'Spatie\\LaravelIgnition\\Recorders\\JobRecorder\\JobRecorder',
      2 => 'Spatie\\LaravelIgnition\\Recorders\\LogRecorder\\LogRecorder',
      3 => 'Spatie\\LaravelIgnition\\Recorders\\QueryRecorder\\QueryRecorder',
    ),
  ),
  'tinker' => 
  array (
    'commands' => 
    array (
    ),
    'alias' => 
    array (
    ),
    'dont_alias' => 
    array (
      0 => 'App\\Nova',
    ),
  ),
);
