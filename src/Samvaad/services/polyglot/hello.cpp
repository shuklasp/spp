#include <iostream>
#include <string>

using namespace std;

int main(int argc, char* argv[]) {
    if (argc < 2) {
        cout << "{\"error\": \"No arguments provided\"}" << endl;
        return 0;
    }

    string input = argv[1];
    string name = "Unknown";

    // Very basic JSON extraction for showcase
    size_t namePos = input.find("\"name\"");
    if (namePos != string::npos) {
        size_t colon = input.find(":", namePos);
        size_t quote1 = input.find("\"", colon);
        size_t quote2 = input.find("\"", quote1 + 1);
        if (quote1 != string::npos && quote2 != string::npos) {
            name = input.substr(quote1 + 1, quote2 - quote1 - 1);
        }
    }

    cout << "{" << endl;
    cout << "  \"status\": \"success\"," << endl;
    cout << "  \"lang\": \"C++\"," << endl;
    cout << "  \"greeting\": \"Hello " << name << " from C++!\"," << endl;
    cout << "  \"received_data\": " << input << endl;
    cout << "}" << endl;

    return 0;
}
