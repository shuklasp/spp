import json
import os
import subprocess
import mysql.connector

class SPP:
    _config = None
    _db = None
    _spp_cli_path = None

    @staticmethod
    def init(config_path=None):
        base_dir = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
        SPP._spp_cli_path = os.path.join(base_dir, 'spp.php')
        
        if config_path is None:
            # Detect bridge_config.json
            config_path = os.path.join(base_dir, 'var', 'shared', 'bridge_config.json')
            if not os.path.exists(config_path):
                # Fallback check
                config_path = os.path.join(base_dir, '..', 'var', 'shared', 'bridge_config.json')
        
        if os.path.exists(config_path):
            with open(config_path, 'r') as f:
                SPP._config = json.load(f)
        else:
            raise Exception(f"SPP Bridge configuration not found at {config_path}")

    @staticmethod
    def db():
        if SPP._db is None:
            if SPP._config is None:
                SPP.init()
            
            db_conf = SPP._config.get('database', {})
            SPP._db = mysql.connector.connect(
                host=db_conf.get('dbhost'),
                user=db_conf.get('dbuser'),
                password=db_conf.get('dbpasswd'),
                database=db_conf.get('dbname')
            )
        return SPP._db

    @staticmethod
    def get_config(key, section='bridge_settings'):
        if SPP._config is None:
            SPP.init()
        return SPP._config.get(section, {}).get(key)
        
    @staticmethod
    def call_php(class_name, method_name, args=None):
        """
        Seamlessly invoke a PHP method via the CLI bridge.
        """
        if SPP._config is None:
            SPP.init()
            
        if args is None:
            args = []
            
        args_json = json.dumps(args)
        
        cmd = ['php', SPP._spp_cli_path, 'bridge:call', class_name, method_name, args_json]
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            output = result.stdout.strip()
            
            # The CLI might output warnings or errors before JSON.
            # We will attempt to find the JSON payload.
            json_start = output.find('{')
            if json_start != -1:
                parsed = json.loads(output[json_start:])
                if parsed.get('success'):
                    return parsed.get('data')
                else:
                    raise Exception(f"PHP Bridge Error: {parsed.get('error')}")
            else:
                raise Exception(f"Invalid response from PHP bridge: {output}")
        except subprocess.CalledProcessError as e:
            raise Exception(f"PHP CLI Execution failed: {e.stderr}")

# Auto-initialize if config exists in default location
try:
    SPP.init()
except:
    pass
