import time
import os

print("Simulating heavy AI model load (takes 2 seconds)...")
time.sleep(2)
print("Model loaded!")

def generate(prompt):
    return f"AI says: Hello from python daemon! You asked: {prompt}"
