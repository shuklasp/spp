package spp

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io/ioutil"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
)

type Config struct {
	Database       map[string]string            `json:"database"`
	BridgeSettings map[string]string            `json:"bridge_settings"`
	Modules        map[string]string            `json:"modules"`
	Raw            map[string]interface{}       `json:"-"`
}

var (
	bridgeConfig *Config
	sppCliPath   string
)

func Init(configPath string) error {
	// Find base dir by navigating up from the current file's directory
	// In Go, since this is compiled, runtime.Caller can get the source file location
	_, filename, _, ok := runtime.Caller(0)
	var baseDir string
	if ok {
		baseDir = filepath.Dir(filepath.Dir(filepath.Dir(filepath.Dir(filepath.Dir(filename)))))
	} else {
		// Fallback to current working dir
		baseDir, _ = os.Getwd()
		baseDir = filepath.Dir(filepath.Dir(baseDir))
	}

	sppCliPath = filepath.Join(baseDir, "spp.php")

	if configPath == "" {
		configPath = filepath.Join(baseDir, "var", "shared", "bridge_config.json")
		if _, err := os.Stat(configPath); os.IsNotExist(err) {
			// Fallback check
			configPath = filepath.Join(baseDir, "..", "var", "shared", "bridge_config.json")
		}
	}

	data, err := ioutil.ReadFile(configPath)
	if err != nil {
		return fmt.Errorf("SPP Bridge configuration not found at %s: %v", configPath, err)
	}

	bridgeConfig = &Config{}
	err = json.Unmarshal(data, bridgeConfig)
	if err != nil {
		return err
	}

	// Also parse into raw for arbitrary nested structures
	json.Unmarshal(data, &bridgeConfig.Raw)

	return nil
}

func GetConfig(key string, section string) string {
	if bridgeConfig == nil {
		Init("")
	}
	
	if section == "bridge_settings" && bridgeConfig.BridgeSettings != nil {
		return bridgeConfig.BridgeSettings[key]
	}
	if section == "database" && bridgeConfig.Database != nil {
		return bridgeConfig.Database[key]
	}
	if section == "modules" && bridgeConfig.Modules != nil {
		return bridgeConfig.Modules[key]
	}

	return ""
}

func CallPhp(className string, methodName string, args interface{}) (interface{}, error) {
	if bridgeConfig == nil {
		Init("")
	}

	argsBytes, err := json.Marshal(args)
	if err != nil {
		return nil, err
	}

	cmd := exec.Command("php", sppCliPath, "bridge:call", className, methodName, string(argsBytes))
	var out bytes.Buffer
	var stderr bytes.Buffer
	cmd.Stdout = &out
	cmd.Stderr = &stderr

	err = cmd.Run()
	if err != nil {
		return nil, fmt.Errorf("PHP CLI Execution failed: %s (%v)", stderr.String(), err)
	}

	output := strings.TrimSpace(out.String())
	jsonStart := strings.Index(output, "{")
	if jsonStart == -1 {
		return nil, fmt.Errorf("Invalid response from PHP bridge: %s", output)
	}

	var result map[string]interface{}
	err = json.Unmarshal([]byte(output[jsonStart:]), &result)
	if err != nil {
		return nil, err
	}

	if success, ok := result["success"].(bool); ok && success {
		return result["data"], nil
	}

	if errMsg, ok := result["error"].(string); ok {
		return nil, errors.New("PHP Bridge Error: " + errMsg)
	}

	return nil, errors.New("Unknown PHP Bridge Error")
}

func init() {
	// Attempt auto-init
	Init("")
}
