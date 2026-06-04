package main

import (
	"fmt"
	"time"

	"github.com/spp/polyglot"
)

func main() {
	fmt.Println("Simulating heavy Go startup (takes 2 seconds)...")
	time.Sleep(2 * time.Second)
	fmt.Println("Go module initialized.")

	polyglot.Serve(dispatch)
}

func dispatch(funcName string, args []interface{}) interface{} {
	if funcName == "generate" {
		prompt, ok := args[0].(string)
		if !ok {
			prompt = "Unknown"
		}
		return "Go AI says: Hello! You asked: " + prompt
	}
	return map[string]string{"error": "Function not found"}
}
