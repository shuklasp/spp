package spp.polyglot;

import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.net.ServerSocket;
import java.net.Socket;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.Map;
import java.util.List;

public class PolyglotBridge {
    
    public interface Dispatcher {
        Object dispatch(String funcName, Object args);
    }

    public static void runDaemon(String portFile, Dispatcher dispatcher) {
        try (ServerSocket serverSocket = new ServerSocket(0)) {
            int port = serverSocket.getLocalPort();
            Files.write(Paths.get(portFile), String.valueOf(port).getBytes());

            while (true) {
                Socket clientSocket = serverSocket.accept();
                new Thread(() -> handleClient(clientSocket, dispatcher)).start();
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private static void handleClient(Socket clientSocket, Dispatcher dispatcher) {
        try (
            BufferedReader in = new BufferedReader(new InputStreamReader(clientSocket.getInputStream()));
            BufferedWriter out = new BufferedWriter(new OutputStreamWriter(clientSocket.getOutputStream()))
        ) {
            String data = in.readLine();
            if (data == null) return;
            
            // Very simple JSON parsing for demonstration, real code would use a JSON library like Gson or Jackson
            // We assume payload is structured like {"func": "name", "args": [...]}
            // For this minimal implementation without pulling dependencies, we extract the function name and pass the raw JSON args string to the user dispatch to handle.
            String funcName = extractJsonString(data, "\"func\"");
            String argsJson = extractJsonValue(data, "\"args\"");
            
            Object result = dispatcher.dispatch(funcName, argsJson);
            
            String jsonResponse;
            if (result instanceof String) {
                // If it looks like raw json or just a plain string. 
                // Let's just wrap it in quotes if it doesn't start with { or [
                String resStr = (String)result;
                if (resStr.startsWith("{") || resStr.startsWith("[")) {
                    jsonResponse = resStr;
                } else {
                    jsonResponse = "\"" + resStr.replace("\"", "\\\"") + "\"";
                }
            } else {
                jsonResponse = "\"" + String.valueOf(result).replace("\"", "\\\"") + "\"";
            }
            
            out.write(jsonResponse + "\n");
            out.flush();
        } catch (Exception e) {
            e.printStackTrace();
        } finally {
            try { clientSocket.close(); } catch (Exception ignore) {}
        }
    }

    public static void serve(String[] args, Dispatcher dispatcher) {
        if (args.length >= 2 && args[0].equals("--daemon")) {
            runDaemon(args[1], dispatcher);
            return;
        }

        if (args.length >= 2) {
            String funcName = args[0];
            String funcArgs = args[1];
            Object result = dispatcher.dispatch(funcName, funcArgs);
            if (result instanceof String) {
                String resStr = (String)result;
                if (resStr.startsWith("{") || resStr.startsWith("[")) {
                    System.out.println(resStr);
                } else {
                    System.out.println("\"" + resStr.replace("\"", "\\\"") + "\"");
                }
            } else {
                System.out.println("\"" + String.valueOf(result).replace("\"", "\\\"") + "\"");
            }
        }
    }

    // Naive JSON extraction (since Java has no built-in JSON)
    private static String extractJsonString(String json, String key) {
        int keyIndex = json.indexOf(key);
        if (keyIndex == -1) return "unknown";
        int colonIndex = json.indexOf(":", keyIndex);
        int startQuote = json.indexOf("\"", colonIndex);
        int endQuote = json.indexOf("\"", startQuote + 1);
        if (startQuote != -1 && endQuote != -1) {
            return json.substring(startQuote + 1, endQuote);
        }
        return "unknown";
    }

    private static String extractJsonValue(String json, String key) {
        int keyIndex = json.indexOf(key);
        if (keyIndex == -1) return "[]";
        int colonIndex = json.indexOf(":", keyIndex);
        String sub = json.substring(colonIndex + 1).trim();
        if (sub.endsWith("}")) {
            sub = sub.substring(0, sub.length() - 1).trim();
        }
        return sub;
    }
}
