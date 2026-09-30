' connect to a server, by memmaker

servername = arg1

if servername = "" then failargs

if try_connect(servername) = 0 then fail

println "[[;#2f2;]Connection to " + servername + " established..]"

if file_exists(".hidden/banner.txt") = 1 then showbanner

goto end

failargs:
println "[[;#f22;]Missing argument. Please specify a server name.]"

goto end

fail:
println "[[;#f22;]Could not establish a connection to the Server " + servername + "]"

goto end

showbanner:

lines = file(".hidden/banner.txt")
linecount = count(lines)						' get the length of the array
counter = 0                                     ' init the counter for the loop
println ""

beginloop:

println get_element(lines, counter) 	        ' output the current filename
counter = counter + 1                           ' increment the loop counter
if counter < linecount then beginloop           ' break the loop when all files are output

end: