using System;
using System.Text.Json;
using System.Threading.Tasks;
using Spp.Polyglot;

class Program
{
    static void Main(string[] args)
    {
        Console.WriteLine("Simulating heavy .NET startup (takes 2 seconds)...");
        Task.Delay(2000).Wait();
        Console.WriteLine(".NET module initialized.");

        Bridge.Serve(args, Dispatch);
    }

    static object Dispatch(string funcName, JsonElement args)
    {
        if (funcName == "generate")
        {
            string prompt = "Unknown";
            if (args.ValueKind == JsonValueKind.Array && args.GetArrayLength() > 0)
            {
                prompt = args[0].GetString();
            }
            return $".NET AI says: Hello! You asked: {prompt}";
        }
        return new { error = "Function not found" };
    }
}
