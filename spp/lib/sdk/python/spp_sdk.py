import sys
import json
import traceback

class SPPService:
    @staticmethod
    def listen(service_name, callback):
        """
        Listen for incoming requests from the SPP Polyglot Bridge via STDIN.
        """
        try:
            # Read single line from stdin
            line = sys.stdin.readline()
            if not line:
                return

            try:
                payload = json.loads(line)
            except json.JSONDecodeError:
                SPPService._respond({"error": "Invalid JSON payload"})
                return

            try:
                # Call the user-defined callback with the parsed JSON payload
                result = callback(payload)
                SPPService._respond({"status": "success", "data": result})
            except Exception as e:
                # Capture the exception traceback
                err_msg = str(e)
                stack = traceback.format_exc()
                SPPService._respond({"status": "error", "error": err_msg, "trace": stack})

        except KeyboardInterrupt:
            pass

    @staticmethod
    def _respond(data):
        """
        Write the JSON response back to STDOUT for the SPP Bridge.
        """
        sys.stdout.write(json.dumps(data) + "\n")
        sys.stdout.flush()
