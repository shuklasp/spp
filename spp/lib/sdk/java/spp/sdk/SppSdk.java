package spp.sdk;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.util.function.Function;

// Basic dependency-free implementation for the SPP SDK
// Requires a JSON parser like Gson or Jackson in the actual service
public class SppSdk {
    
    public interface SppCallback {
        String handle(String jsonPayload) throws Exception;
    }

    public static void listen(String serviceName, SppCallback callback) {
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(System.in))) {
            String line;
            while ((line = reader.readLine()) != null) {
                try {
                    String resultJson = callback.handle(line);
                    // The callback is expected to return the inner data JSON
                    System.out.println("{\"status\":\"success\",\"data\":" + resultJson + "}");
                } catch (Exception e) {
                    System.out.println("{\"status\":\"error\",\"error\":\"" + escapeJson(e.getMessage()) + "\"}");
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private static String escapeJson(String text) {
        if (text == null) return "";
        return text.replace("\"", "\\\"");
    }
}
