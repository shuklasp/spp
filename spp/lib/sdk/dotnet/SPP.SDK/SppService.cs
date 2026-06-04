using System;
using System.Text.Json;

namespace SPP.SDK
{
    public class SppService
    {
        public static void Listen(string serviceName, Func<JsonElement, object> callback)
        {
            string line;
            while ((line = Console.ReadLine()) != null)
            {
                try
                {
                    JsonElement payload = JsonSerializer.Deserialize<JsonElement>(line);
                    object result = callback(payload);
                    var response = new { status = "success", data = result };
                    Console.WriteLine(JsonSerializer.Serialize(response));
                }
                catch (Exception ex)
                {
                    var errorResponse = new { status = "error", error = ex.Message, trace = ex.StackTrace };
                    Console.WriteLine(JsonSerializer.Serialize(errorResponse));
                }
            }
        }
    }
}
