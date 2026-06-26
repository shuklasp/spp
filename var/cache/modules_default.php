<?php
// SPP Compiled Module Registry - DO NOT EDIT
return array(
  'sppdb' =>
    array(
      'name' => 'sppdb',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppdb',
      'type' => 'system',
      'version' => '1.2',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'sppdb',
          'pubname' => 'SPP Database Core',
          'version' => 1.2,
          'author' => 'Satya Prakash Shukla',
          'pubdesc' => 'Core database abstraction layer providing prefixed table mapping, transaction management, and incremental schema synchronization.',
          'modgroup' => 'Core',
          'category' => 'Core Required',
          'settings' =>
            array(
              'dbtype' =>
                array(
                  'type' => 'select',
                  'label' => 'Database Engine',
                  'help' => 'Select the primary database technology. This choice determines which connection parameters (Host, Port, User) are required below.',
                  'options' =>
                    array(
                      'mysql' => 'MySQL / MariaDB',
                      'sqlite' => 'SQLite (File-based)',
                      'pgsql' => 'PostgreSQL',
                      'mssql' => 'Microsoft SQL Server',
                      'oracledb' => 'Oracle Database',
                      'msaccess' => 'MS Access',
                      'xdb' => 'SPP XDB (XML)',
                    ),
                  'default' => 'mysql',
                ),
              'dbhost' =>
                array(
                  'type' => 'text',
                  'label' => 'Hostname / IP Address',
                  'help' => 'The network location of your database server. Use "localhost" or "127.0.0.1" if the server is running on this same machine.',
                  'depends_on' =>
                    array(
                      'dbtype' =>
                        array(
                          0 => 'mysql',
                          1 => 'pgsql',
                          2 => 'mssql',
                          3 => 'oracledb',
                          4 => 'xdb',
                        ),
                    ),
                  'default' => 'localhost',
                ),
              'dbport' =>
                array(
                  'type' => 'number',
                  'label' => 'Network Port',
                  'help' => 'The port number the database service is listening on (Standard defaults: MySQL: 3306, PostgreSQL: 5432, MSSQL: 1433).',
                  'depends_on' =>
                    array(
                      'dbtype' =>
                        array(
                          0 => 'mysql',
                          1 => 'pgsql',
                          2 => 'mssql',
                          3 => 'oracledb',
                          4 => 'xdb',
                        ),
                    ),
                  'default' => 3306,
                ),
              'dbuser' =>
                array(
                  'type' => 'text',
                  'label' => 'Administrative Username',
                  'help' => 'The database user account. For development, "root" or "sa" is common; for production, use a dedicated application user.',
                  'depends_on' =>
                    array(
                      'dbtype' =>
                        array(
                          0 => 'mysql',
                          1 => 'pgsql',
                          2 => 'mssql',
                          3 => 'oracledb',
                          4 => 'xdb',
                        ),
                    ),
                  'default' => 'root',
                ),
              'dbpasswd' =>
                array(
                  'type' => 'password',
                  'label' => 'Security Password',
                  'help' => 'Authentication secret for the specified user. Leave blank ONLY if your database server allows passwordless local connections (not recommended).',
                  'depends_on' =>
                    array(
                      'dbtype' =>
                        array(
                          0 => 'mysql',
                          1 => 'pgsql',
                          2 => 'mssql',
                          3 => 'oracledb',
                          4 => 'xdb',
                        ),
                    ),
                  'default' => NULL,
                ),
              'dbname' =>
                array(
                  'type' => 'text',
                  'label' => 'Database Name / Schema',
                  'help' => 'The specific database or schema where the SPP tables will be installed. This must exist before the module can sync schemas.',
                  'depends_on' =>
                    array(
                      'dbtype' =>
                        array(
                          0 => 'mysql',
                          1 => 'pgsql',
                          2 => 'mssql',
                          3 => 'oracledb',
                          4 => 'xdb',
                        ),
                    ),
                  'default' => 'school',
                ),
              'sqlite_path' =>
                array(
                  'type' => 'text',
                  'label' => 'SQLite Database Path',
                  'help' => 'The filesystem path to your .sqlite database file. This should be relative to the SPP application root.',
                  'depends_on' =>
                    array(
                      'dbtype' =>
                        array(
                          0 => 'sqlite',
                          1 => 'msaccess',
                        ),
                    ),
                  'default' => 'var/db/school.sqlite',
                ),
              'table_prefix' =>
                array(
                  'type' => 'text',
                  'label' => 'Global Table Prefix',
                  'help' => 'A short string (e.g., "spp_") prepended to all table names to avoid collisions with other applications sharing the same database.',
                  'default' => NULL,
                ),
            ),
        ),
      'has_modinit' => true,
    ),
  'dbconfig' =>
    array(
      'name' => 'dbconfig',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/dbconfig',
      'type' => 'system',
      'version' => '1.1',
      'dependencies' =>
        array(
          0 => 'sppdb',
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'dbconfig',
          'pubname' => 'Database Configuration',
          'version' => 1.1,
          'author' => 'Satya Prakash Shukla',
          'pubdesc' => 'Database configuration extensions for SPP.',
          'modgroup' => 'Core',
          'category' => 'Core Required',
          'deps' =>
            array(
              0 => 'sppdb',
            ),
        ),
      'has_modinit' => true,
    ),
  'sppauth' =>
    array(
      'name' => 'sppauth',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppauth',
      'type' => 'system',
      'version' => '0.5',
      'dependencies' =>
        array(
          0 => 'sppdb',
          1 => 'dbconfig',
        ),
      'includes' =>
        array(
          0 => 'class.sppauth.php',
          1 => 'class.sppright.php',
          2 => 'class.spprole.php',
          3 => 'class.sppuser.php',
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'sppauth',
          'version' => '0.5',
          'pubname' => 'SPP Auth',
          'modgroup' => 'Core',
          'category' => 'Core Required',
          'pubdesc' => 'Handles native SPP Authentication.',
          'includes' =>
            array(
              0 => 'class.sppauth.php',
              1 => 'class.sppright.php',
              2 => 'class.spprole.php',
              3 => 'class.sppuser.php',
            ),
          'deps' =>
            array(
              0 => 'sppdb',
              1 => 'dbconfig',
            ),
        ),
      'has_modinit' => true,
    ),
  'spplogger' =>
    array(
      'name' => 'spplogger',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/spplogger',
      'type' => 'system',
      'version' => '1.2',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'spplogger',
          'pubname' => 'SPP Unified Logger',
          'version' => 1.2,
          'author' => 'Satya Prakash Shukla',
          'pubdesc' => 'Centralized logging engine providing multiple adapters (Database, File, Console) with support for log levels and rotation.',
          'modgroup' => 'Core',
          'category' => 'Core Required',
          'settings' =>
            array(
              'log_precedence' =>
                array(
                  'type' => 'select',
                  'label' => 'Log Dispatch Priority',
                  'help' => 'Defines the order of priority for log message distribution. "Database First" ensures transactional integrity before local file write.',
                  'options' =>
                    array(
                      'db_first' => 'Database First',
                      'file_first' => 'File First',
                      'parallel' => 'Parallel Writing',
                    ),
                  'default' => 'db_first',
                ),
              'log_targets' =>
                array(
                  'type' => 'text',
                  'label' => 'Active Log Sinks',
                  'help' => 'Comma-separated list of enabled adapters. Valid sinks: "db" (SQL persistence), "file" (Local filesystem), "syslog" (OS system log).',
                  'default' => 'db, file',
                ),
              'log_subdir' =>
                array(
                  'type' => 'text',
                  'label' => 'Log Storage Directory',
                  'help' => 'Relative directory inside the global var/logs folder where this module will deposit files.',
                  'default' => 'system',
                ),
              'log_filename_format' =>
                array(
                  'type' => 'text',
                  'label' => 'Filename Pattern',
                  'help' => 'Naming convention for generated log files. Available tokens: {appname} (Active context), {date} (YYYY-MM-DD), {index} (Rotation counter).',
                  'default' => 'log-{appname}-{date}-{index}.log',
                ),
              'max_file_size' =>
                array(
                  'type' => 'number',
                  'label' => 'Rotation Threshold (Bytes)',
                  'help' => 'Individual file size limit before the logger initiates rotation. Default is 2MB (2,097,152 bytes).',
                  'default' => 2097152,
                ),
              'log_retention_days' =>
                array(
                  'type' => 'number',
                  'label' => 'Retention Policy (Days)',
                  'help' => 'Number of days historical log entries will be preserved before automated cleanup tasks purge them.',
                  'default' => 30,
                ),
            ),
        ),
      'has_modinit' => true,
    ),
  'sppview' =>
    array(
      'name' => 'spphtml',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppview',
      'type' => 'system',
      'version' => '0.5',
      'dependencies' =>
        array(
          0 => 'sppdb',
          1 => 'dbconfig',
        ),
      'includes' =>
        array(
          0 => 'class.viewtag.php',
          1 => 'class.sppformelement.php',
          2 => 'class.phpcomponent.php',
          3 => 'class.jsgenerator.php',
          4 => 'class.viewvalidator.php',
          5 => 'class.viewpage.php',
          6 => 'class.viewassetmanager.php',
          7 => 'class.viewformdispatcher.php',
          8 => 'class.viewrenderer.php',
          9 => 'class.viewrouter.php',
          10 => 'class.viewform.php',
          11 => 'class.ajax.php',
          12 => 'ajaxexceptions.php',
          13 => 'formelements/classes.formelements.php',
          14 => 'sppvalidator/class.validationresult.php',
          15 => 'sppvalidator/class.sppsinglevalidator.php',
          16 => 'sppvalidator/class.sppmultiplevalidator.php',
          17 => 'sppvalidator/classes.sppvalidators.php',
          18 => 'class.viewformbuilder.php',
          19 => 'class.formaugmentor.php',
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
          0 => 'page_source_primary',
          1 => 'page_source_fallback',
          2 => 'auto_page_augmentation',
          3 => 'auto_js_injection',
        ),
      'raw_manifest' =>
        array(
          'name' => 'spphtml',
          'version' => '0.5',
          'pubname' => 'SPP View',
          'modgroup' => 'Core',
          'category' => 'Core Required',
          'pubdesc' => 'Handles HTML in SPP.',
          'includes' =>
            array(
              0 => 'class.viewtag.php',
              1 => 'class.sppformelement.php',
              2 => 'class.phpcomponent.php',
              3 => 'class.jsgenerator.php',
              4 => 'class.viewvalidator.php',
              5 => 'class.viewpage.php',
              6 => 'class.viewassetmanager.php',
              7 => 'class.viewformdispatcher.php',
              8 => 'class.viewrenderer.php',
              9 => 'class.viewrouter.php',
              10 => 'class.viewform.php',
              11 => 'class.ajax.php',
              12 => 'ajaxexceptions.php',
              13 => 'formelements/classes.formelements.php',
              14 => 'sppvalidator/class.validationresult.php',
              15 => 'sppvalidator/class.sppsinglevalidator.php',
              16 => 'sppvalidator/class.sppmultiplevalidator.php',
              17 => 'sppvalidator/classes.sppvalidators.php',
              18 => 'class.viewformbuilder.php',
              19 => 'class.formaugmentor.php',
            ),
          'deps' =>
            array(
              0 => 'sppdb',
              1 => 'dbconfig',
            ),
          'config_variables' =>
            array(
              0 => 'page_source_primary',
              1 => 'page_source_fallback',
              2 => 'auto_page_augmentation',
              3 => 'auto_js_injection',
            ),
          'settings' =>
            array(
              'page_source_primary' =>
                array(
                  'type' => 'select',
                  'label' => 'Primary Page Source',
                  'options' =>
                    array(
                      'yaml' => 'YAML Definition',
                      'db' => 'Database Store',
                      'file' => 'Physical File',
                    ),
                  'default' => 'yaml',
                ),
              'page_source_fallback' =>
                array(
                  'type' => 'select',
                  'label' => 'Fallback Page Source',
                  'options' =>
                    array(
                      'yaml' => 'YAML Definition',
                      'db' => 'Database Store',
                      'file' => 'Physical File',
                      'none' => 'None',
                    ),
                  'default' => 'db',
                ),
              'auto_page_augmentation' =>
                array(
                  'type' => 'boolean',
                  'label' => 'Auto Page Augmentation',
                  'default' => true,
                ),
              'auto_js_injection' =>
                array(
                  'type' => 'boolean',
                  'label' => 'Auto Js Injection',
                  'default' => true,
                ),
            ),
        ),
      'has_modinit' => true,
    ),
  'sppapi' =>
    array(
      'name' => 'sppapi',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppapi',
      'type' => 'system',
      'version' => '1.0',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
          'require_key' => '1',
          'enable_jwt' => '1',
          'jwt_secret' => '',
          'jwt_expires_in' => '3600',
        ),
      'raw_manifest' =>
        array(
          'name' => 'sppapi',
          'author' => 'SPP Framework Builder',
          'version' => '1.0',
          'description' => 'Zero-Code API engine interpreting YAML configurations into RESTful data endpoints.',
          'category' => 'Core Optional',
          'namespace' => 'SPPMod\\SPPAPI',
          'autoload' =>
            array(
              0 =>
                array(
                  'class' => 'SPPAPI',
                  'file' => 'class.sppapi.php',
                ),
              1 =>
                array(
                  'class' => 'SPPAjax',
                  'file' => 'class.sppajax.php',
                ),
              2 =>
                array(
                  'class' => 'LiveAction',
                  'file' => 'class.liveaction.php',
                ),
              3 =>
                array(
                  'class' => 'JWTAuth',
                  'file' => 'src/JWTAuth.php',
                ),
              4 =>
                array(
                  'class' => 'Subscribers\\ApiErrorSubscriber',
                  'file' => 'Subscribers/ApiErrorSubscriber.php',
                ),
              5 =>
                array(
                  'class' => 'Dispatchers\\ComponentDispatcher',
                  'file' => 'Dispatchers/ComponentDispatcher.php',
                ),
              6 =>
                array(
                  'class' => 'Dispatchers\\ServiceDispatcher',
                  'file' => 'Dispatchers/ServiceDispatcher.php',
                ),
              7 =>
                array(
                  'class' => 'Dispatchers\\PageDispatcher',
                  'file' => 'Dispatchers/PageDispatcher.php',
                ),
              8 =>
                array(
                  'class' => 'Dispatchers\\StreamDispatcher',
                  'file' => 'Dispatchers/StreamDispatcher.php',
                ),
              9 =>
                array(
                  'class' => 'Dispatchers\\IntentDispatcher',
                  'file' => 'Dispatchers/IntentDispatcher.php',
                ),
              10 =>
                array(
                  'class' => 'Dispatchers\\CdcDispatcher',
                  'file' => 'Dispatchers/CdcDispatcher.php',
                ),
              11 =>
                array(
                  'class' => 'Middleware\\ApiThrottleMiddleware',
                  'file' => 'Middleware/ApiThrottleMiddleware.php',
                ),
              12 =>
                array(
                  'class' => 'Middleware\\ApiAuthMiddleware',
                  'file' => 'Middleware/ApiAuthMiddleware.php',
                ),
              13 =>
                array(
                  'class' => 'Controllers\\EntityGetController',
                  'file' => 'Controllers/EntityGetController.php',
                ),
              14 =>
                array(
                  'class' => 'Controllers\\EntityPostController',
                  'file' => 'Controllers/EntityPostController.php',
                ),
              15 =>
                array(
                  'class' => 'Controllers\\EntityPutPatchController',
                  'file' => 'Controllers/EntityPutPatchController.php',
                ),
              16 =>
                array(
                  'class' => 'Controllers\\EntityDeleteController',
                  'file' => 'Controllers/EntityDeleteController.php',
                ),
              17 =>
                array(
                  'class' => 'SPPApiResource',
                  'file' => 'class.sppapiresource.php',
                ),
              18 =>
                array(
                  'class' => 'SPPApiResponse',
                  'file' => 'class.sppapiresponse.php',
                ),
              19 =>
                array(
                  'class' => 'SPPPaginator',
                  'file' => 'class.spppaginator.php',
                ),
              20 =>
                array(
                  'class' => 'SPPRouteModelBinding',
                  'file' => 'class.spproutemodelbinding.php',
                ),
            ),
          'config_variables' =>
            array(
              'require_key' => true,
              'enable_jwt' => true,
              'jwt_secret' => '',
              'jwt_expires_in' => 3600,
            ),
        ),
      'has_modinit' => true,
    ),
  'drishyam' =>
    array(
      'name' => 'drishyam',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/drishyam',
      'type' => 'system',
      'version' => '2.0',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
          0 => 'class.sppblade.php',
          1 => 'class.sppux.php',
          2 => 'class.spppwa.php',
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
          'views_path' => 'resources/views',
          'cache_path' => 'var/cache/blade',
          'mode' => 'auto',
        ),
      'raw_manifest' =>
        array(
          'name' => 'drishyam',
          'version' => '2.0',
          'pubname' => 'SPP Drishyam',
          'modgroup' => 'Core',
          'category' => 'Core Required',
          'pubdesc' => 'Consolidated Frontend Engine (Blade templating, PWA, SPPUX Components).',
          'includes' =>
            array(
              0 => 'class.sppblade.php',
              1 => 'class.sppux.php',
              2 => 'class.spppwa.php',
            ),
          'services' =>
            array(
              'blade' =>
                array(
                  'class' => '\\SPPMod\\Drishyam\\SPPBlade',
                  'shared' => true,
                ),
            ),
          'config_variables' =>
            array(
              'views_path' => 'resources/views',
              'cache_path' => 'var/cache/blade',
              'mode' => 'auto',
            ),
        ),
      'has_modinit' => true,
    ),
  'parikshak' =>
    array(
      'name' => 'parikshak',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/parikshak',
      'type' => 'system',
      'version' => '1.0.0',
      'dependencies' =>
        array(
          0 => 'sppdb',
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'parikshak',
          'version' => '1.0.0',
          'description' => 'Automated Evolutionary Testing Engine (Evaluator)',
          'author' => 'Satya Prakash Shukla',
          'category' => 'Core Optional',
          'namespace' => 'SPPMod\\Parikshak',
          'config' => 'config.yml',
          'dependencies' =>
            array(
              0 => 'sppdb',
            ),
          'settings' =>
            array(
              'active' =>
                array(
                  'type' => 'boolean',
                  'label' => 'Active',
                  'help' => 'Globally enable or disable the evolutionary testing engine.',
                  'default' => true,
                ),
              'fuzz_intensity' =>
                array(
                  'type' => 'number',
                  'label' => 'Fuzz Intensity',
                  'help' => 'Number of mutation iterations per entity field during testing. Higher values increase coverage but take longer.',
                  'default' => 10,
                ),
              'auto_generate_tests' =>
                array(
                  'type' => 'boolean',
                  'label' => 'Auto Generate Tests',
                  'help' => 'Automatically discover new entities and generate test suites for them.',
                  'default' => true,
                ),
              'table_prefix' =>
                array(
                  'type' => 'text',
                  'label' => 'Table Prefix',
                  'help' => 'Prefix used for temporary testing tables to avoid conflict with production data.',
                  'default' => 'spptest__',
                ),
              'storage_strategy' =>
                array(
                  'type' => 'select',
                  'label' => 'Storage Strategy',
                  'help' => 'Choose whether to run tests in the same database or an isolated one.',
                  'options' =>
                    array(
                      'same_db' => 'Same Database',
                      'isolated' => 'Isolated Database',
                    ),
                  'default' => 'same_db',
                ),
            ),
        ),
      'has_modinit' => false,
    ),
  'sppai' =>
    array(
      'name' => 'sppai',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppai',
      'type' => 'system',
      'version' => '1.0',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'sppai',
          'author' => 'SPP Auto-Builder',
          'version' => '1.0',
          'description' => 'SPPAI Universal AI Gateway module with multi-provider support.',
          'category' => 'Core Optional',
          'namespace' => 'SPPMod\\SPPAI',
          'config' => 'etc/config.yml',
          'autoload' =>
            array(
              0 =>
                array(
                  'class' => 'SPPAI',
                  'file' => 'class.sppai.php',
                ),
              1 =>
                array(
                  'interface' => 'AIDriverInterface',
                  'file' => 'int.aidriver.php',
                ),
              2 =>
                array(
                  'class' => 'GeminiDriver',
                  'file' => 'drivers/class.geminidriver.php',
                ),
              3 =>
                array(
                  'class' => 'ChatGPTDriver',
                  'file' => 'drivers/class.chatgptdriver.php',
                ),
              4 =>
                array(
                  'class' => 'ClaudeDriver',
                  'file' => 'drivers/class.claudedriver.php',
                ),
              5 =>
                array(
                  'class' => 'DeepSeekDriver',
                  'file' => 'drivers/class.deepseekdriver.php',
                ),
              6 =>
                array(
                  'class' => 'SarvamDriver',
                  'file' => 'drivers/class.sarvamdriver.php',
                ),
            ),
        ),
      'has_modinit' => false,
    ),
  'lekhni' =>
    array(
      'name' => 'lekhni',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/contrib/lekhni',
      'type' => 'system',
      'version' => '1.2.0',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'lekhni',
          'version' => '1.2.0',
          'description' => 'Premium Modular Block, Dual-Mode Code Editor & Ultimate Enterprise Document Engine',
          'category' => 'Core Optional',
          'author' => 'SPP Core Team',
          'settings' =>
            array(
              'editor' =>
                array(
                  'default_mode' => 'document',
                  'code_language' => 'html',
                  'theme' => 'dark',
                  'tab_size' => 2,
                  'auto_save_interval' => 2000,
                  'enable_ai_copilot' => true,
                  'enable_markdown_expansion' => true,
                  'media_upload_path' => 'var/media/lekhni',
                  'categories' =>
                    array(
                      0 => 'General',
                      1 => 'News',
                      2 => 'Tutorial',
                      3 => 'Engineering',
                      4 => 'Documentation',
                    ),
                ),
            ),
        ),
      'has_modinit' => false,
    ),
  'sppxdb' =>
    array(
      'name' => 'sppxdb',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppxdb',
      'type' => 'system',
      'version' => '1.0',
      'dependencies' =>
        array(
          0 => 'sppdb',
          1 => 'dbconfig',
        ),
      'includes' =>
        array(
          0 => 'sppxdb.php',
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'sppxdb',
          'version' => '1.0',
          'pubname' => 'SPP XDB',
          'modgroup' => 'SPP_Optional',
          'pubdesc' => 'Handles XML Database in SPP with XQuery and SQL support.',
          'category' => 'Core Optional',
          'includes' =>
            array(
              0 => 'sppxdb.php',
            ),
          'deps' =>
            array(
              0 => 'sppdb',
              1 => 'dbconfig',
            ),
          'settings' =>
            array(
              'sys:db.engine' =>
                array(
                  'type' => 'select',
                  'label' => 'XDB Engine',
                  'options' =>
                    array(
                      'xml' => 'XML Engine (Legacy)',
                      'sqlite' => 'SQLite Engine (High Performance)',
                    ),
                  'default' => 'xml',
                  'description' => 'The backing storage engine for SPPXDB operations.',
                ),
            ),
        ),
      'has_modinit' => false,
    ),
  'sppmigrate' =>
    array(
      'name' => 'SPPMigrate',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppmigrate',
      'type' => 'system',
      'version' => '1.0.0',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'SPPMigrate',
          'description' => 'Lifecycle deployment and migration module.',
          'version' => '1.0.0',
          'author' => 'Antigravity',
          'type' => 'module',
          'core_version_requirement' => '>=1.0.0',
          'dependencies' =>
            array(
            ),
        ),
      'has_modinit' => false,
    ),
  'sppsecurity' =>
    array(
      'name' => 'sppsecurity',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/sppsecurity',
      'type' => 'system',
      'version' => '1.0.0',
      'dependencies' =>
        array(
          0 => 'sppcore',
          1 => 'sppsession',
          2 => 'dbconfig',
        ),
      'includes' =>
        array(
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'sppsecurity',
          'description' => 'Provides core security implementations (CSRF, Rate Limiting, Sanitization)',
          'version' => '1.0.0',
          'type' => 'module',
          'author' => 'SPP Core Team',
          'status' => 'active',
          'deps' =>
            array(
              0 => 'sppcore',
              1 => 'sppsession',
              2 => 'dbconfig',
            ),
        ),
      'has_modinit' => false,
    ),
  'spprouter' =>
    array(
      'name' => 'spprouter',
      'path' => 'C:\\projects\\apache\\school1\\spp/modules/spp/spprouter',
      'type' => 'system',
      'version' => '1.0.0',
      'dependencies' =>
        array(
        ),
      'includes' =>
        array(
          0 => 'class.spprouter.php',
        ),
      'services' =>
        array(
        ),
      'config' =>
        array(
        ),
      'raw_manifest' =>
        array(
          'name' => 'spprouter',
          'version' => '1.0.0',
          'type' => 'core',
          'compulsory' => true,
          'publicname' => 'SPP Router',
          'publicdesc' => 'Decoupled Routing Engine for HTTP dispatch.',
          'includes' =>
            array(
              0 => 'class.spprouter.php',
            ),
        ),
      'has_modinit' => true,
    ),
  '__meta' =>
    array(
      'manifest_mtime' => 1782202792,
    ),
);
