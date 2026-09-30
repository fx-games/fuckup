' creation of a user on the mainframe, by memmaker

' parameters
username = arg1
password = arg2

' initialization
println "Trying to create account on MainFrame for user " + username

println "Checking username parameter"
if username = "" then failNoUsername

println "Checking password parameter"
if password = "" then failNoPassword

println "Checking for available username"
if user_exists(username) then failUserExists

println "Creating user"
create_user(username, password)
println "[[;#2f2;]User " + username + " created successfully]"
goto end

failNoUsername:
println "[[;#f22;]No username provided.]"
goto end

failNoPassword:
println "[[;#f22;]No password provided.]"
goto end

failUserExists:
println "[[;#f22;]User already exists.]"


end:
