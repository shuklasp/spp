package services.java;

import spp.polyglot.PolyglotBridge;
import spp.polyglot.PolyglotBridge.Dispatcher;

public class DaemonService {
    public static void main(String[] args) throws InterruptedException {
        System.out.println("Simulating heavy Java startup (takes 2 seconds)...");
        Thread.sleep(2000);
        System.out.println("Java module initialized.");

        Dispatcher dispatch = (funcName, argsJson) -> {
            if ("generate".equals(funcName)) {
                String prompt = "Unknown";
                String json = (String)argsJson;
                if (json.startsWith("[") && json.endsWith("]")) {
                    String inner = json.substring(1, json.length() - 1);
                    if (inner.startsWith("\"") && inner.endsWith("\"")) {
                        prompt = inner.substring(1, inner.length() - 1);
                    }
                }
                return "Java AI says: Hello! You asked: " + prompt;
            }
            return "{\"error\": \"Function not found\"}";
        };

        PolyglotBridge.serve(args, dispatch);
    }
}
