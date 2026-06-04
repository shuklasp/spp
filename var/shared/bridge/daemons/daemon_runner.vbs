Set objShell = CreateObject("WScript.Shell")
objShell.CurrentDirectory = WScript.Arguments(1)
objShell.Run WScript.Arguments(0), 0, False
