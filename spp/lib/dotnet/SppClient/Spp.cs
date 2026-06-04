#nullable disable

using System;
using System.Diagnostics;
using System.IO;
using System.Text.Json;
using System.Collections.Generic;

namespace Spp
{
    public class SppClient
    {
        private static JsonElement? _config;
        private static string _sppCliPath;

        public static void Init(string configPath = null)
        {
            if (_config != null) return;

            string baseDir = Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "..", "..", "..", "..", "..", ".."));
            _sppCliPath = Path.Combine(baseDir, "spp.php");

            if (string.IsNullOrEmpty(configPath))
            {
                configPath = Path.Combine(baseDir, "var", "shared", "bridge_config.json");
                if (!File.Exists(configPath))
                {
                    configPath = Path.Combine(baseDir, "..", "var", "shared", "bridge_config.json");
                }
            }

            if (File.Exists(configPath))
            {
                string json = File.ReadAllText(configPath);
                using (JsonDocument doc = JsonDocument.Parse(json))
                {
                    _config = doc.RootElement.Clone();
                }
            }
            else
            {
                throw new Exception($"SPP Bridge configuration not found at {configPath}");
            }
        }

        public static string GetConfig(string key, string section = "bridge_settings")
        {
            if (_config == null) Init();

            if (_config.Value.TryGetProperty(section, out JsonElement sectionElement))
            {
                if (sectionElement.TryGetProperty(key, out JsonElement keyElement))
                {
                    return keyElement.GetString();
                }
            }
            return null;
        }

        public static JsonElement CallPhp(string className, string methodName, object args)
        {
            if (_config == null) Init();

            string argsJson = JsonSerializer.Serialize(args);

            var processInfo = new ProcessStartInfo
            {
                FileName = "php",
                Arguments = $"\"{_sppCliPath}\" bridge:call \"{className}\" \"{methodName}\" \"{argsJson.Replace("\"", "\\\"")}\"",
                RedirectStandardOutput = true,
                RedirectStandardError = true,
                UseShellExecute = false,
                CreateNoWindow = true,
            };

            using (var process = Process.Start(processInfo))
            {
                string output = process.StandardOutput.ReadToEnd();
                string error = process.StandardError.ReadToEnd();
                process.WaitForExit();

                if (process.ExitCode != 0)
                {
                    throw new Exception($"PHP CLI Execution failed: {error}");
                }

                int jsonStart = output.IndexOf('{');
                if (jsonStart == -1)
                {
                    throw new Exception($"Invalid response from PHP bridge: {output}");
                }

                string jsonOut = output.Substring(jsonStart);
                using (JsonDocument doc = JsonDocument.Parse(jsonOut))
                {
                    var root = doc.RootElement;
                    if (root.TryGetProperty("success", out JsonElement success) && success.GetBoolean())
                    {
                        if (root.TryGetProperty("data", out JsonElement data))
                        {
                            return data.Clone();
                        }
                        return default;
                    }
                    else
                    {
                        string errMsg = root.TryGetProperty("error", out JsonElement err) ? err.GetString() : "Unknown Error";
                        throw new Exception($"PHP Bridge Error: {errMsg}");
                    }
                }
            }
        }
    }
}
