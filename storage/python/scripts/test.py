import json
import sys
import platform


def main():
    input_data = json.loads(sys.stdin.read())

    output = {
        "status": "ok",
        "python_version": platform.python_version(),
        "received_input": input_data,
    }

    print(json.dumps(output))


if __name__ == "__main__":
    main()
