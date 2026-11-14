# Records .github/dash.gif, the demo in README.md, with `make demo`.
# rift-dash runs against docker/demo/rift, which serves the contract fixture,
# so the recording never touches Docker or a real project.

Output ".github/dash.gif"

Set Shell bash
Set FontSize 15
Set Width 1440
Set Height 840
Set Padding 16
Set Theme "Catppuccin Mocha"
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
