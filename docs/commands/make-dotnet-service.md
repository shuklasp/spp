# NAME
`make:dotnet-service` - Create a new .NET service project

# SYNOPSIS
`php spp.php make:dotnet-service <name> [--app=context]`

# PURPOSE
The `make:dotnet-service` command scaffolds an external microservice written in C# .NET Core. It automatically provisions a new C# Console Application that binds to the `SppClient` library, allowing native inter-process communication or external execution capabilities alongside the core SPP PHP framework.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target name of the .NET Service.
- `--app=<context>` (string, optional): The application context namespace.

# UNDER THE HOOD ACTIVITY
When triggered, the PHP CLI executes actual OS-level `.NET` commands via `shell_exec()`. It locates the target directory under `services/dotnet/service.{lowercase_name}`. 
1. It runs `dotnet new console -n Service.{Name} -o {projectDir}` to bootstrap a raw .NET C# console application. The project name is escaped using `escapeshellarg()` to prevent command injection.
2. It then computes the absolute path to the SPP base directory and runs `dotnet add {projectDir} reference {SppClient.csproj}`. This physically alters the C# project file to depend on SPP's internal C# interop library (`SppClient`).
3. Finally, it uses the standard SPP `buildFromStub()` mechanism to delete the default C# `Program.cs` and replace it with a highly specialized `dotnet_service` stub, injecting the `CLASS_NAME` directly into the C# source code.

# EXAMPLES
**1. Scaffold an Image Processing microservice in C#:**
```bash
php spp.php make:dotnet-service ImageProcessor
```
