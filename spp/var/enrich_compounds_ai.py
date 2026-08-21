import json
import time
import os
import argparse
import wikipedia
# Try to import the modern ddgs package, fallback to duckduckgo_search
try:
    from ddgs import DDGS
except ImportError:
    import warnings
    with warnings.catch_warnings():
        warnings.simplefilter("ignore")
        from duckduckgo_search import DDGS
from google import genai
from google.genai import types
from tenacity import retry, wait_exponential, stop_after_attempt, retry_if_exception_type

def setup_gemini(api_key):
    # Initialize the modern Google GenAI client
    return genai.Client(api_key=api_key)

def search_duckduckgo(query):
    try:
        with warnings.catch_warnings():
            warnings.simplefilter("ignore")
            results = DDGS().text(query, max_results=3)
            return "\n".join([f"- {r['title']}: {r['body']}" for r in results])
    except Exception as e:
        return ""

def search_wikipedia(query):
    try:
        # Get summary to save tokens
        return wikipedia.summary(query, sentences=5)
    except:
        return ""

@retry(wait=wait_exponential(multiplier=2, min=4, max=60), stop=stop_after_attempt(5))
def call_gemini(client, model_name, prompt):
    return client.models.generate_content(
        model=model_name,
        contents=prompt,
        config=types.GenerateContentConfig(
            temperature=0.7,
        )
    )

def enrich_compound(client, compound, model_name):
    name = compound.get('name', '')
    if not name:
        return compound
        
    print(f"[*] Researching {name}...")
    
    # 1. Gather raw data
    ddg_data = search_duckduckgo(f"{name} uses history chemistry India")
    wiki_data = search_wikipedia(name)
    
    prompt = f"""
You are a master encyclopedic chemist. I have a compound: {name}.
Here is some raw data from the web (Wikipedia & DuckDuckGo):
{wiki_data}
{ddg_data}

Synthesize a massively comprehensive, highly engaging, and deeply informative profile for this compound. 
CRITICAL REQUIREMENT: You MUST include Indian context if available (e.g., historical Ayurvedic uses like Bhasma/Kasisa, geographical deposits in India, or modern Indian industrial/agricultural applications). 

Return ONLY valid JSON matching this exact structure:
{{
    "description": "A rich 2-3 paragraph encyclopedic description synthesizing the compound's nature, history, and Indian context.",
    "uses": "A 1-2 paragraph description of its primary uses, replacing any old comma-separated lists.",
    "facts": [
        "Fascinating fact 1...",
        "Fascinating fact 2...",
        "Fascinating fact 3..."
    ]
}}
Do NOT use markdown blocks like ```json. Just raw valid JSON.
    """
    
    try:
        response = call_gemini(client, model_name, prompt)
        text = response.text.strip()
        if text.startswith("```json"):
            text = text[7:]
        if text.endswith("```"):
            text = text[:-3]
            
        data = json.loads(text)
        
        # Update compound
        compound['description'] = data.get('description', compound.get('description', ''))
        compound['uses'] = data.get('uses', compound.get('uses', ''))
        
        # Merge facts
        existing_facts = compound.get('facts', [])
        new_facts = data.get('facts', [])
        compound['facts'] = list(set(existing_facts + new_facts))
        
        print(f"  -> Successfully synthesized enriched profile for {name}.")
        return compound
    except Exception as e:
        print(f"  -> Failed to synthesize for {name}: {e}")
        return compound

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--api-key', required=True, help="Google Gemini API Key")
    parser.add_argument('--model', default='gemini-2.0-flash', help="Gemini Model Name")
    parser.add_argument('--input', default='c:/projects/apache/school1/src/ptable/data/compounds.json')
    args = parser.parse_args()
    
    client = setup_gemini(args.api_key)
    
    with open(args.input, 'r', encoding='utf-8') as f:
        compounds = json.load(f)
        
    print(f"Loaded {len(compounds)} compounds. Starting deep enrichment daemon...")
    
    # Process one by one, saving frequently
    # We will use a checkpoint system (check if 'enriched' flag is present)
    for i, c in enumerate(compounds):
        if c.get('_enriched'):
            continue
            
        enriched_c = enrich_compound(client, c, args.model)
        enriched_c['_enriched'] = True
        compounds[i] = enriched_c
        
        # Save every 5 compounds to prevent data loss
        if i % 5 == 0:
            with open(args.input, 'w', encoding='utf-8') as f:
                json.dump(compounds, f, indent=2)
                
        # Sleep to respect rate limits (15 RPM for free tier -> 4 seconds per request)
        time.sleep(4)
        
    # Final save
    with open(args.input, 'w', encoding='utf-8') as f:
        json.dump(compounds, f, indent=2)
        
    print("FINISHED enriching all compounds!")

if __name__ == '__main__':
    main()
