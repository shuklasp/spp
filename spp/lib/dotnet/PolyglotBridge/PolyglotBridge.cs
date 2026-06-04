using System;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Text.Json;
using System.Threading.Tasks;
using System.Collections.Generic;

namespace Spp.Polyglot
{
    public class PolyglotRequest
    {
        public string func { get; set; }
        public JsonElement args { get; set; }
    }

    public static class Bridge
    {
        public delegate object Dispatcher(string funcName, JsonElement args);

        public static void RunDaemon(string portFile, Dispatcher dispatch)
        {
            TcpListener server = null;
            try
            {
                server = new TcpListener(IPAddress.Parse("127.0.0.1"), 0);
                server.Start();
                int port = ((IPEndPoint)server.LocalEndpoint).Port;
                File.WriteAllText(portFile, port.ToString());

                while (true)
                {
                    TcpClient client = server.AcceptTcpClient();
                    Task.Run(() => HandleClient(client, dispatch));
                }
            }
            catch (Exception e)
            {
                Console.WriteLine("Exception: {0}", e);
            }
            finally
            {
                server?.Stop();
            }
        }

        private static void HandleClient(TcpClient client, Dispatcher dispatch)
        {
            try
            {
                using (NetworkStream stream = client.GetStream())
                using (StreamReader reader = new StreamReader(stream))
                using (StreamWriter writer = new StreamWriter(stream) { AutoFlush = true })
                {
                    string data = reader.ReadLine();
                    if (data == null) return;

                    var req = JsonSerializer.Deserialize<PolyglotRequest>(data);
                    var result = dispatch(req.func, req.args);

                    string jsonResponse = JsonSerializer.Serialize(result);
                    writer.WriteLine(jsonResponse);
                }
            }
            catch (Exception)
            {
                // Ignore client errors
            }
            finally
            {
                client.Close();
            }
        }

        public static void Serve(string[] args, Dispatcher dispatch)
        {
            if (args.Length >= 2 && args[0] == "--daemon")
            {
                RunDaemon(args[1], dispatch);
                return;
            }

            if (args.Length >= 2)
            {
                string funcName = args[0];
                var funcArgs = JsonSerializer.Deserialize<JsonElement>(args[1]);
                var result = dispatch(funcName, funcArgs);
                Console.WriteLine(JsonSerializer.Serialize(result));
            }
        }
    }
}
