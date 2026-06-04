import sys, json, importlib, os, socket

def load_module(module_name):
    bridge_dir = os.path.dirname(os.path.abspath(__file__))
    if bridge_dir not in sys.path: sys.path.insert(0, bridge_dir)
    
    if "/" in module_name or "\\" in module_name:
        import importlib.util
        if not module_name.endswith(".py"): module_name += ".py"
        spec = importlib.util.spec_from_file_location("dynamic_module", module_name)
        module = importlib.util.module_from_spec(spec)
        sys.modules["dynamic_module"] = module
        spec.loader.exec_module(module)
        return module
    return importlib.import_module(module_name)

def main():
    module_name = sys.argv[1]
    
    if "--daemon" in sys.argv:
        port_file = sys.argv[sys.argv.index("--daemon") + 1]
        try:
            module = load_module(module_name)
        except Exception as e:
            sys.stderr.write(str(e))
            sys.exit(1)
            
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.bind(("127.0.0.1", 0))
        s.listen(5)
        port = s.getsockname()[1]
        with open(port_file, "w") as f: f.write(str(port))
        
        while True:
            conn, addr = s.accept()
            data = b""
            while b"\n" not in data:
                chunk = conn.recv(4096)
                if not chunk: break
                data += chunk
            if not data:
                conn.close()
                continue
            try:
                req = json.loads(data.decode("utf-8").strip())
                func = getattr(module, req["func"])
                args = req.get("args", [])
                if isinstance(args, list): res = func(*args)
                elif isinstance(args, dict): res = func(**args)
                else: res = func()
                conn.sendall(json.dumps(res).encode("utf-8") + b"\n")
            except Exception as e:
                pass
            finally:
                conn.close()
    else:
        func_name = sys.argv[2]
        try:
            module = load_module(module_name)
            args_raw = sys.stdin.read()
            args = json.loads(args_raw) if args_raw else []
            func = getattr(module, func_name)
            if isinstance(args, list): result = func(*args)
            elif isinstance(args, dict): result = func(**args)
            else: result = func()
            print(json.dumps(result))
        except Exception as e:
            sys.stderr.write(str(e))
            sys.exit(1)

if __name__ == "__main__":
    main()