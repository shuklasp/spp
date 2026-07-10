package main

import (
	"encoding/json"
	"fmt"
	"os"
)

func main() {
	if len(os.Args) < 2 {
		output, _ := json.Marshal(map[string]string{"error": "No arguments provided"})
		fmt.Println(string(output))
		return
	}

	var data map[string]interface{}
	err := json.Unmarshal([]byte(os.Args[1]), &data)
	
	if err != nil {
		output, _ := json.Marshal(map[string]string{"error": err.Error()})
		fmt.Println(string(output))
		return
	}

	name := "Unknown"
	if n, ok := data["name"].(string); ok {
		name = n
	}

	response := map[string]interface{}{
		"status":        "success",
		"lang":          "Go",
		"greeting":      fmt.Sprintf("Hello %s from Go!", name),
		"received_data": data,
	}

	output, _ := json.Marshal(response)
	fmt.Println(string(output))
}
