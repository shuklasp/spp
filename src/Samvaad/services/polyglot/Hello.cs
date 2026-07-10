// Hello.cs
// In a real application, this would be a compiled .NET project using System.Text.Json.
// The backend showcase controller intercepts this specific script and mocks the result 
// to avoid the overhead of scaffolding a full .NET CLI project dynamically.

using System;

class Hello
{
    static void Main(string[] args)
    {
        Console.WriteLine("{ \"greeting\": \"Hello SPP User from C#!\", \"lang\": \"C#\", \"status\": \"success\" }");
    }
}
