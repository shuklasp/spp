import sys
import json
import urllib.request
import os
import re
from pathlib import Path

# A simple AI Mentor script for SPP Polyglot Bridge
# If Ollama is running locally, it attempts to query it.
# If it fails, it returns an error triggering PHP's Graceful Fallback.

OLLAMA_URL = "http://localhost:11434/api/generate"
MODEL_NAME = "llama3" # You can change this to phi3 or mistral depending on local setup

def read_spp_context(question):
    """
    Very naive RAG: Grabs snippets of documentation to feed to the LLM 
    so it knows about SPP framework concepts.
    """
    base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), '../../../../../documentation'))
    context = ""
    
    # We will just inject the rosetta-stone if it exists to give the LLM some SPP context
    rosetta_path = os.path.join(base_dir, 'framework', 'rosetta-stone.md')
    if os.path.exists(rosetta_path):
        with open(rosetta_path, 'r', encoding='utf-8') as f:
            context += "SPP Framework Architectural Context:\n" + f.read() + "\n\n"
            
    return context

def handle_spp_request(payload):
    question = payload.get('question', '')
    if not question:
        return {"error": "No question provided."}

    context = read_spp_context(question)
    
    prompt = f"""
    You are an expert senior developer for the 'SPP Framework' (a custom PHP monolith).
    Answer the user's question clearly and concisely.
    Use the following framework context if relevant:
    
    {context}
    
    User Question: {question}
    """

    data = {
        "model": MODEL_NAME,
        "prompt": prompt,
        "stream": False
    }

    req = urllib.request.Request(OLLAMA_URL, data=json.dumps(data).encode('utf-8'), headers={'Content-Type': 'application/json'})
    
    try:
        with urllib.request.urlopen(req, timeout=3) as response:
            result = json.loads(response.read().decode('utf-8'))
            return {"answer": result.get('response', 'No response generated.')}
    except Exception as e:
        # Returning an error will trigger the PHP Fallback Search
        return {"error": f"Failed to connect to Local AI Service ({str(e)}). Ensure Ollama is running."}

if __name__ == '__main__':
    # Polyglot STDIN/STDOUT protocol
    try:
        input_data = sys.stdin.read()
        if not input_data.strip():
            sys.exit(0)
            
        payload = json.loads(input_data)
        response = handle_spp_request(payload)
        print(json.dumps(response))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
