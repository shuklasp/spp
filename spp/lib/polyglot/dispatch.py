import sys, json, importlib, os, socket, re, time, secrets

def load_module(module_name):
    bridge_dir = os.path.dirname(os.path.abspath(__file__))
    if bridge_dir not in sys.path:
        sys.path.insert(0, bridge_dir)

    module_abs = os.path.abspath(module_name)
    module_dir = os.path.dirname(module_abs)
    if module_dir not in sys.path:
        sys.path.insert(0, module_dir)

    if "/" in module_name or "\\" in module_name or os.path.exists(module_abs):
        import importlib.util
        if not module_name.endswith(".py") and not os.path.exists(module_abs):
            module_abs += ".py"
        spec = importlib.util.spec_from_file_location("dynamic_module", module_abs)
        if spec is None or spec.loader is None:
            raise ImportError(f"Cannot load module from spec: {module_abs}")
        module = importlib.util.module_from_spec(spec)
        sys.modules["dynamic_module"] = module
        spec.loader.exec_module(module)
        return module
    return importlib.import_module(module_name)

def main():
    if len(sys.argv) < 2:
        sys.stderr.write("Usage: dispatch.py <module_name> [<func_name> | --daemon <port_file>] [--auth-token <token>] [--pid-file <pid_file>]\n")
        sys.exit(1)

    module_name = sys.argv[1]

    if "--daemon" in sys.argv:
        port_file = sys.argv[sys.argv.index("--daemon") + 1]
        auth_token = ""
        if "--auth-token" in sys.argv:
            auth_token = sys.argv[sys.argv.index("--auth-token") + 1]
        
        pid_file = ""
        if "--pid-file" in sys.argv:
            pid_file = sys.argv[sys.argv.index("--pid-file") + 1]

        try:
            module = load_module(module_name)
        except Exception as e:
            sys.stderr.write(f"Failed to load module {module_name}: {e}\n")
            sys.exit(1)

        # Bind loopback TCP socket
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        s.bind(("127.0.0.1", 0))
        s.listen(16)
        port = s.getsockname()[1]

        # Atomic port file write
        tmp_port = port_file + ".tmp." + str(os.getpid())
        with open(tmp_port, "w", encoding="utf-8") as f:
            f.write(str(port))
        try:
            if os.path.exists(port_file):
                os.remove(port_file)
            os.replace(tmp_port, port_file)
        except Exception:
            try:
                os.rename(tmp_port, port_file)
            except Exception:
                pass

        # Write PID file if requested
        if pid_file:
            tmp_pid = pid_file + ".tmp." + str(os.getpid())
            with open(tmp_pid, "w", encoding="utf-8") as f:
                f.write(str(os.getpid()))
            try:
                if os.path.exists(pid_file):
                    os.remove(pid_file)
                os.replace(tmp_pid, pid_file)
            except Exception:
                try:
                    os.rename(tmp_pid, pid_file)
                except Exception:
                    pass

        while True:
            try:
                conn, addr = s.accept()
                conn.settimeout(15.0)
            except Exception:
                continue

            data = b""
            max_bytes = 10 * 1024 * 1024 # 10MB limit
            try:
                while b"\n" not in data:
                    chunk = conn.recv(8192)
                    if not chunk:
                        break
                    data += chunk
                    if len(data) > max_bytes:
                        conn.sendall(json.dumps({
                            "success": False,
                            "error": "Payload limit exceeded (max 10MB)",
                            "error_code": 413
                        }).encode("utf-8") + b"\n")
                        conn.close()
                        data = b""
                        break
                
                if not data:
                    conn.close()
                    continue

                req = json.loads(data.decode("utf-8").strip())
                req_id = req.get("request_id", "")

                # 1. Authenticate Request
                if auth_token:
                    client_token = req.get("auth_token", "")
                    if not secrets.compare_digest(str(client_token), str(auth_token)):
                        conn.sendall(json.dumps({
                            "success": False,
                            "error": "Unauthorized: invalid or missing auth_token",
                            "error_code": 401,
                            "request_id": req_id
                        }).encode("utf-8") + b"\n")
                        conn.close()
                        continue

                # 2. Built-in Control Commands (ping, shutdown)
                func_name = req.get("func", req.get("action", ""))
                if func_name in ("__ping__", "ping"):
                    conn.sendall(json.dumps({
                        "success": True,
                        "data": "PONG",
                        "status": "ready",
                        "pid": os.getpid(),
                        "request_id": req_id
                    }).encode("utf-8") + b"\n")
                    conn.close()
                    continue

                if func_name in ("__shutdown__", "shutdown"):
                    conn.sendall(json.dumps({
                        "success": True,
                        "message": "Worker shutting down",
                        "request_id": req_id
                    }).encode("utf-8") + b"\n")
                    conn.close()
                    break

                # 3. Validate function name identifier
                if not re.match(r"^[a-zA-Z0-9_]+$", func_name):
                    conn.sendall(json.dumps({
                        "success": False,
                        "error": f"Invalid function identifier '{func_name}'",
                        "error_code": 400,
                        "request_id": req_id
                    }).encode("utf-8") + b"\n")
                    conn.close()
                    continue

                if not hasattr(module, func_name):
                    conn.sendall(json.dumps({
                        "success": False,
                        "error": f"Function '{func_name}' not found in module '{module_name}'",
                        "error_code": 404,
                        "request_id": req_id
                    }).encode("utf-8") + b"\n")
                    conn.close()
                    continue

                func = getattr(module, func_name)
                args = req.get("args", [])
                if isinstance(args, list):
                    res = func(*args)
                elif isinstance(args, dict):
                    res = func(**args)
                else:
                    res = func()

                conn.sendall(json.dumps({
                    "success": True,
                    "data": res,
                    "request_id": req_id
                }).encode("utf-8") + b"\n")
            except Exception as e:
                try:
                    conn.sendall(json.dumps({
                        "success": False,
                        "error": str(e),
                        "error_type": type(e).__name__,
                        "request_id": req.get("request_id", "") if "req" in locals() and isinstance(req, dict) else ""
                    }).encode("utf-8") + b"\n")
                except Exception:
                    pass
            finally:
                try:
                    conn.close()
                except Exception:
                    pass

        try:
            s.close()
        except Exception:
            pass
        sys.exit(0)

    else:
        # Ephemeral subprocess mode
        func_name = sys.argv[2]
        try:
            module = load_module(module_name)
            args_raw = sys.stdin.read()
            args = json.loads(args_raw) if args_raw else []
            func = getattr(module, func_name)
            if isinstance(args, list):
                result = func(*args)
            elif isinstance(args, dict):
                result = func(**args)
            else:
                result = func()
            print(json.dumps(result))
        except Exception as e:
            sys.stderr.write(str(e))
            sys.exit(1)

if __name__ == "__main__":
    main()
