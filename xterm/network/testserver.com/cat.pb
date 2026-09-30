' show the contents of a file, by memmaker

filename = arg1
if filename = "" then failargs

lines = file(filename)
linecount = count(lines)						' get the length of the array
counter = 0                                     ' init the counter for the loop
println ""

beginloop:

println get_element(lines, counter) 	        ' output the current line
counter = counter + 1                           ' increment the loop counter
if counter < linecount then beginloop           ' break the loop when all files are output

goto end

failargs:
println "[[;#f22;]Missing argument. Please specify a filename.]"

end: