' query the FUCKUP intel database, by memmaker

keyword = arg1
if keyword = "" then failargs

println "Querying the FUCKUP intel database for '" + keyword + "'.."
println ""

entry = get_intel(keyword)

if entry = "" then noentry
println "[[;#2f2;]" + keyword + ":]"
println entry
goto end

noentry:
println "[[;#f22;]No entry found.]"
goto end

failargs:
println "[[;#f22;]Missing argument. Please specify a keyword for the query.]"

end: