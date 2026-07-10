public class Hello {
    public static void main(String[] args) {
        if (args.length < 1) {
            System.out.println("{\"error\": \"No arguments provided\"}");
            return;
        }

        String input = args[0];
        String name = "Unknown";
        
        // Simple extraction for showcase (assumes {"name":"..."})
        if (input.contains("\"name\"")) {
            int start = input.indexOf("\"name\"");
            int colon = input.indexOf(":", start);
            int quote1 = input.indexOf("\"", colon);
            int quote2 = input.indexOf("\"", quote1 + 1);
            if (quote1 != -1 && quote2 != -1) {
                name = input.substring(quote1 + 1, quote2);
            }
        }
        
        // Output raw JSON
        System.out.println("{");
        System.out.println("  \"status\": \"success\",");
        System.out.println("  \"lang\": \"Java\",");
        System.out.println("  \"greeting\": \"Hello " + name + " from Java!\",");
        System.out.println("  \"received_data\": " + input);
        System.out.println("}");
    }
}
