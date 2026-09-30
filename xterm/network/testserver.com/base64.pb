' base64 en- and decoding of file contents, by memmaker

command = arg1
filename = arg2

if command = "" then failargs
if filename = "" then failargs

content = file_get_contents(filename)

if arg1 = "encode" then encode
if arg1 = "decode" then decode

goto failargs

encode:
println base64_encode(content)

goto end

decode:
println base64_decode(content)

goto end

failargs:
println "Missing argument. Please specify a command and a filename."

end: