package polyglot

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net"
	"os"
	"strconv"
)

type PolyglotRequest struct {
	Func string        `json:"func"`
	Args []interface{} `json:"args"`
}

type Dispatcher func(funcName string, args []interface{}) interface{}

func RunDaemon(portFile string, dispatch Dispatcher) {
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		fmt.Printf("Error starting daemon: %v\n", err)
		os.Exit(1)
	}
	defer listener.Close()

	port := listener.Addr().(*net.TCPAddr).Port
	err = os.WriteFile(portFile, []byte(strconv.Itoa(port)), 0644)
	if err != nil {
		fmt.Printf("Error writing port file: %v\n", err)
		os.Exit(1)
	}

	for {
		conn, err := listener.Accept()
		if err != nil {
			continue
		}
		go handleConnection(conn, dispatch)
	}
}

func handleConnection(conn net.Conn, dispatch Dispatcher) {
	defer conn.Close()

	reader := bufio.NewReader(conn)
	data, err := reader.ReadString('\n')
	if err != nil {
		return
	}

	var req PolyglotRequest
	if err := json.Unmarshal([]byte(data), &req); err != nil {
		return
	}

	result := dispatch(req.Func, req.Args)

	respData, err := json.Marshal(result)
	if err != nil {
		return
	}

	conn.Write(append(respData, '\n'))
}

func Serve(dispatch Dispatcher) {
	if len(os.Args) >= 3 && os.Args[1] == "--daemon" {
		RunDaemon(os.Args[2], dispatch)
		return
	}

	// Ephemeral mode fallback
	if len(os.Args) >= 3 {
		funcName := os.Args[1]
		var args []interface{}
		if err := json.Unmarshal([]byte(os.Args[2]), &args); err == nil {
			res := dispatch(funcName, args)
			out, _ := json.Marshal(res)
			fmt.Println(string(out))
		}
	}
}
