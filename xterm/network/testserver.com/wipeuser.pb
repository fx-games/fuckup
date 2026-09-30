' wipes a user account from the MainFrame, by memmaker

ack = arg1
if ack = "" then failAck

wipeIt:
wipe_user()

println "User has been deleted. Goodbye."

goto end

failAck:
println "Please specify any argument as ACK"


end: