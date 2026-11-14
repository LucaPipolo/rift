# Records .github/dash.gif, the demo in README.md, with `make demo`.
# rift-dash runs against docker/demo/rift, which serves the contract fixture,
# so the recording never touches Docker or a real project.

Output ".github/dash.gif"

Set Shell bash
Set FontSize 15
Set Width 1440
Set Height 840
Set Padding 16
# The docs site's dark palette: ink, with tangerine as rift-dash's cyan accent.
Set Theme {"name":"Rift","background":"#1c1c1a","foreground":"#e5e5e2","cursor":"#ef6433","selection":"#41413e","black":"#1c1c1a","red":"#e8394a","green":"#c6f96e","yellow":"#fcea5d","blue":"#f4b052","magenta":"#f08bad","cyan":"#ef6433","white":"#cfcfcb","brightBlack":"#575753","brightRed":"#f06a77","brightGreen":"#d8fb9b","brightYellow":"#fdf08d","brightBlue":"#f7c47f","brightMagenta":"#f5abc4","brightCyan":"#f48a62","brightWhite":"#ffffff"}
Set TypingSpeed 120ms
Set Framerate 20

Hide
Type `cd /app && RIFT_COMMAND='["sh","/app/docker/demo/rift"]' builds/rift-dash-demo`
Enter
Sleep 1.5s
Show

Sleep 2s
Down Sleep 1.2s
Type "]" Sleep 1.4s
Type "]" Sleep 2s
Type "]" Sleep 1.4s
Type "]" Sleep 1.4s
Type "[[[[" Sleep 500ms
Down Sleep 900ms
Down Sleep 900ms
Down Sleep 1.2s
Type "/" Sleep 400ms
Type "fix" Sleep 1.2s
Escape Sleep 600ms
Type "3" Sleep 2s
Type "2" Sleep 600ms
Type ":" Sleep 600ms
Type "res" Sleep 1.4s
Escape Sleep 600ms
Type "?" Sleep 2.5s
Escape Sleep 1s

Hide
Type "q"
