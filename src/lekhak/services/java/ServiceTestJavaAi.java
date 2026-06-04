import java.io.InputStream;
import java.util.Scanner;
import spp.Spp;

public class ServiceTestJavaAi {
    public static void main(String[] args) {
        try {
            // Read JSON input from PHP via stdin
            Scanner scanner = new Scanner(System.in).useDelimiter("\\A");
            String inputRaw = scanner.hasNext() ? scanner.next() : "";

            // Example of calling PHP natively:
            // String phpResult = Spp.callPhp("App\\Services\\NativeService", "someMethod", "[\"hello\"]");

            // Business Logic
            // We use basic string manipulation for JSON to avoid forcing external dependencies like Jackson/Gson
            // In a real app, you can add your preferred JSON parser to the classpath
            String inputEscaped = inputRaw.replace("\"", "\\\"");
            
            String outputJson = "{" +
                "\"status\":\"success\"," +
                "\"message\":\"Hello from Java ServiceTestJavaAi service!\"," +
                "\"received_args\":\"" + inputEscaped + "\"" +
            "}";

            // Output JSON to stdout
            System.out.print(outputJson);
        } catch (Exception e) {
            System.err.println(e.getMessage());
            System.exit(1);
        }
    }
}
