package spp;

import java.io.File;
import java.io.InputStream;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.Scanner;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class Spp {
    private static String configJson;
    private static String sppCliPath;

    public static void init() throws Exception {
        init(null);
    }

    public static void init(String configPath) throws Exception {
        if (configJson != null) return;

        // Path resolution assumes we are running in the context of the SPP workspace
        String baseDir = new File(System.getProperty("user.dir")).getAbsolutePath();
        
        // Find base dir (if executed inside src/services/...)
        File checkDir = new File(baseDir);
        while (checkDir != null && !new File(checkDir, "spp.php").exists()) {
            checkDir = checkDir.getParentFile();
        }
        
        if (checkDir != null) {
            baseDir = checkDir.getAbsolutePath();
        }
        
        sppCliPath = new File(baseDir, "spp.php").getAbsolutePath();

        if (configPath == null) {
            configPath = new File(baseDir, "var/shared/bridge_config.json").getAbsolutePath();
            if (!new File(configPath).exists()) {
                configPath = new File(baseDir, "../var/shared/bridge_config.json").getAbsolutePath();
            }
        }

        if (new File(configPath).exists()) {
            configJson = new String(Files.readAllBytes(Paths.get(configPath)));
        } else {
            throw new Exception("SPP Bridge configuration not found at " + configPath);
        }
    }

    public static String getConfig(String key) throws Exception {
        return getConfig(key, "bridge_settings");
    }

    public static String getConfig(String key, String section) throws Exception {
        if (configJson == null) init();
        
        // Very basic JSON regex parsing for simplicity without external dependencies (Jackson/Gson)
        String sectionPattern = "\"" + section + "\"\\s*:\\s*\\{([^}]*)\\}";
        Matcher matcher = Pattern.compile(sectionPattern).matcher(configJson);
        if (matcher.find()) {
            String sectionContent = matcher.group(1);
            String keyPattern = "\"" + key + "\"\\s*:\\s*\"([^\"]*)\"";
            Matcher keyMatcher = Pattern.compile(keyPattern).matcher(sectionContent);
            if (keyMatcher.find()) {
                return keyMatcher.group(1);
            }
            // Check for numeric/boolean values
            String numPattern = "\"" + key + "\"\\s*:\\s*([^,\\s]+)";
            Matcher numMatcher = Pattern.compile(numPattern).matcher(sectionContent);
            if (numMatcher.find()) {
                return numMatcher.group(1);
            }
        }
        return null;
    }

    public static String callPhp(String className, String methodName, String argsJson) throws Exception {
        if (configJson == null) init();

        String argsEscaped = argsJson.replace("\"", "\\\"");
        
        ProcessBuilder pb = new ProcessBuilder("php", sppCliPath, "bridge:call", className, methodName, argsJson);
        pb.redirectErrorStream(true);
        Process process = pb.start();
        
        InputStream is = process.getInputStream();
        Scanner scanner = new Scanner(is).useDelimiter("\\A");
        String output = scanner.hasNext() ? scanner.next() : "";
        
        int exitCode = process.waitFor();
        if (exitCode != 0) {
            throw new Exception("PHP CLI Execution failed: " + output);
        }
        
        int jsonStart = output.indexOf('{');
        if (jsonStart == -1) {
            throw new Exception("Invalid response from PHP bridge: " + output);
        }
        
        String jsonOut = output.substring(jsonStart);
        // Basic check for success
        if (jsonOut.contains("\"success\":true") || jsonOut.contains("\"success\": true")) {
            // Extract data block - naive extraction for this SDK without full JSON parser
            Matcher m = Pattern.compile("\"data\"\\s*:\\s*(.*)\\}?$").matcher(jsonOut);
            if (m.find()) {
                String dataStr = m.group(1);
                if (dataStr.endsWith("}")) dataStr = dataStr.substring(0, dataStr.length()-1);
                return dataStr.trim();
            }
            return jsonOut;
        } else {
            throw new Exception("PHP Bridge Error: " + jsonOut);
        }
    }
}
