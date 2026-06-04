#pragma once
#include <iostream>
#include <string>
#include <functional>
#include <fstream>
#include <thread>
#include <vector>

#ifdef _WIN32
    #include <winsock2.h>
    #include <ws2tcpip.h>
    #pragma comment(lib, "ws2_32.lib")
#else
    #include <sys/socket.h>
    #include <netinet/in.h>
    #include <unistd.h>
#endif

namespace polyglot {

    using Dispatcher = std::function<std::string(const std::string&, const std::string&)>;

    inline std::string extractJsonString(const std::string& json, const std::string& key) {
        size_t keyPos = json.find(key);
        if (keyPos == std::string::npos) return "unknown";
        size_t colonPos = json.find(":", keyPos);
        if (colonPos == std::string::npos) return "unknown";
        size_t startQuote = json.find("\"", colonPos);
        if (startQuote == std::string::npos) return "unknown";
        size_t endQuote = json.find("\"", startQuote + 1);
        if (endQuote == std::string::npos) return "unknown";
        return json.substr(startQuote + 1, endQuote - startQuote - 1);
    }

    inline std::string extractJsonValue(const std::string& json, const std::string& key) {
        size_t keyPos = json.find(key);
        if (keyPos == std::string::npos) return "[]";
        size_t colonPos = json.find(":", keyPos);
        if (colonPos == std::string::npos) return "[]";
        
        std::string sub = json.substr(colonPos + 1);
        size_t firstChar = sub.find_first_not_of(" \t\r\n");
        if (firstChar != std::string::npos) sub = sub.substr(firstChar);
        size_t lastChar = sub.find_last_not_of(" \t\r\n");
        if (lastChar != std::string::npos) sub = sub.substr(0, lastChar + 1);
        
        if (!sub.empty() && sub.back() == '}') {
            sub.pop_back();
            lastChar = sub.find_last_not_of(" \t\r\n");
            if (lastChar != std::string::npos) sub = sub.substr(0, lastChar + 1);
        }
        return sub;
    }

    inline void handleClient(int clientSocket, Dispatcher dispatcher) {
        char buffer[4096];
        std::string data;
        while (true) {
#ifdef _WIN32
            int bytesRead = recv(clientSocket, buffer, sizeof(buffer) - 1, 0);
#else
            int bytesRead = read(clientSocket, buffer, sizeof(buffer) - 1);
#endif
            if (bytesRead <= 0) break;
            buffer[bytesRead] = '\0';
            data += buffer;
            if (data.find('\n') != std::string::npos) break;
        }

        if (!data.empty()) {
            std::string funcName = extractJsonString(data, "\"func\"");
            std::string argsJson = extractJsonValue(data, "\"args\"");

            std::string result = dispatcher(funcName, argsJson);
            
            std::string response;
            if (result.rfind("{", 0) == 0 || result.rfind("[", 0) == 0) {
                response = result + "\n";
            } else {
                // Escape quotes in simple string response
                std::string escaped;
                for (char c : result) {
                    if (c == '"') escaped += "\\\"";
                    else escaped += c;
                }
                response = "\"" + escaped + "\"\n";
            }

#ifdef _WIN32
            send(clientSocket, response.c_str(), response.length(), 0);
#else
            write(clientSocket, response.c_str(), response.length());
#endif
        }

#ifdef _WIN32
        closesocket(clientSocket);
#else
        close(clientSocket);
#endif
    }

    inline void runDaemon(const std::string& portFile, Dispatcher dispatcher) {
#ifdef _WIN32
        WSADATA wsaData;
        WSAStartup(MAKEWORD(2, 2), &wsaData);
#endif

        int serverSocket = socket(AF_INET, SOCK_STREAM, 0);
        
        sockaddr_in serverAddr;
        serverAddr.sin_family = AF_INET;
        serverAddr.sin_addr.s_addr = INADDR_ANY;
        serverAddr.sin_port = 0; // OS assigned port

        bind(serverSocket, (struct sockaddr*)&serverAddr, sizeof(serverAddr));
        listen(serverSocket, 5);

        socklen_t len = sizeof(serverAddr);
        getsockname(serverSocket, (struct sockaddr*)&serverAddr, &len);
        int port = ntohs(serverAddr.sin_port);

        std::ofstream pf(portFile);
        pf << port;
        pf.close();

        while (true) {
            int clientSocket = accept(serverSocket, nullptr, nullptr);
            if (clientSocket >= 0) {
                std::thread(handleClient, clientSocket, dispatcher).detach();
            }
        }

#ifdef _WIN32
        closesocket(serverSocket);
        WSACleanup();
#else
        close(serverSocket);
#endif
    }

    inline void serve(int argc, char* argv[], Dispatcher dispatcher) {
        if (argc >= 3 && std::string(argv[1]) == "--daemon") {
            runDaemon(argv[2], dispatcher);
            return;
        }

        if (argc >= 3) {
            std::string funcName = argv[1];
            std::string funcArgs = argv[2];
            std::string result = dispatcher(funcName, funcArgs);
            if (result.rfind("{", 0) == 0 || result.rfind("[", 0) == 0) {
                std::cout << result << std::endl;
            } else {
                std::string escaped;
                for (char c : result) {
                    if (c == '"') escaped += "\\\"";
                    else escaped += c;
                }
                std::cout << "\"" << escaped << "\"" << std::endl;
            }
        }
    }

}
