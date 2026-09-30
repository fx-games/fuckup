' changing the terminal width, by memmaker

println "[[;#f22;]WARNING!] Setting your terminal width to less than 80 characters can have undesired side effects."

newwidth = arg1

if newwidth = "" then failargs

term_width(newwidth)

println "[[;#2f2;]Success.] Your terminal has been adjusted."

goto end

failargs:
println "[[;#f22;]Missing argument. Please specify the new terminal width.]"

goto end

end: