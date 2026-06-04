package sppsdk

import (
	"bufio"
	"encoding/json"
	"fmt"
	"os"
)

type Payload map[string]interface{}

type SPPResponse struct {
	Status string      `json:"status"`
	Data   interface{} `json:"data,omitempty"`
	Error  string      `json:"error,omitempty"`
	Trace  string      `json:"trace,omitempty"`
}

func Listen(serviceName string, callback func(Payload) (interface{}, error)) {
	scanner := bufio.NewScanner(os.Stdin)
	for scanner.Scan() {
		line := scanner.Text()
		var payload Payload
		
		if err := json.Unmarshal([]byte(line), &payload); err != nil {
			respond(SPPResponse{Status: "error", Error: "Invalid JSON payload"})
			continue
		}

		result, err := callback(payload)
		if err != nil {
			respond(SPPResponse{Status: "error", Error: err.Error()})
		} else {
			respond(SPPResponse{Status: "success", Data: result})
		}
	}
}

func respond(data SPPResponse) {
	bytes, err := json.Marshal(data)
	if err == nil {
		fmt.Println(string(bytes))
	}
}
