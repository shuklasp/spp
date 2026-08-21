import sys
from google import genai

api_key = sys.argv[1] if len(sys.argv) > 1 else None
if not api_key:
    print("Usage: python list_models.py API_KEY")
    sys.exit(1)

client = genai.Client(api_key=api_key)
print("Available models:")
for m in client.models.list():
    print(f" - {m.name}")
