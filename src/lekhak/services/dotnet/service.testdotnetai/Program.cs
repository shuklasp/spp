using System;
using System.Text.Json;
using Spp;

namespace Service.TestDotNetAi
{
    class Program
    {
        static void Main(string[] args)
        {
            // Read input from PHP via stdin
            string inputRaw = Console.In.ReadToEnd();
            JsonElement? inputArgs = null;
            if (!string.IsNullOrWhiteSpace(inputRaw))
            {
                using (JsonDocument doc = JsonDocument.Parse(inputRaw))
                {
                    inputArgs = doc.RootElement.Clone();
                }
            }

            // Example of calling PHP natively:
            // var phpResult = SppClient.CallPhp("App\\Services\\NativeService", "someMethod", new[] { "hello" });

            // Business Logic
            var resp = new
            {
                status = "success",
                message = "Hello from .NET TestDotNetAi service!",
                args = inputArgs
            };

            // Output JSON to stdout
            string outputJson = JsonSerializer.Serialize(resp);
            Console.Write(outputJson);
        }
    }
}
