#include <iostream>
#include <chrono>
#include "../../spp/lib/cpp/polyglot.hpp"

std::string dispatch(const std::string& funcName, const std::string& argsJson) {
    if (funcName == "generate") {
        std::string prompt = "Unknown";
        if (argsJson.front() == '[' && argsJson.back() == ']') {
            std::string inner = argsJson.substr(1, argsJson.length() - 2);
            if (inner.front() == '"' && inner.back() == '"') {
                prompt = inner.substr(1, inner.length() - 2);
            }
        }
        return "C++ AI says: Hello! You asked: " + prompt;
    }
    return "{\"error\": \"Function not found\"}";
}

int main(int argc, char* argv[]) {
    std::cout << "Simulating heavy C++ startup (takes 2 seconds)..." << std::endl;
    std::this_thread::sleep_for(std::chrono::seconds(2));
    std::cout << "C++ module initialized." << std::endl;

    polyglot::serve(argc, argv, dispatch);
    return 0;
}
