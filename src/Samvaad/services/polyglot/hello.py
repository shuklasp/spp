import sys
import json

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No arguments provided"}))
        return
        
    try:
        data = json.loads(sys.argv[1])
        name = data.get('name', 'Unknown')
        
        response = {
            "status": "success",
            "lang": "Python 3",
            "greeting": f"Hello {name} from Python!",
            "received_data": data
        }
        print(json.dumps(response))
    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
