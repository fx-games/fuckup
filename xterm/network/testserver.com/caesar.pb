' simple caesar shift cipher implemented in pBasic, by memmaker

' parameters
shift = arg1
filename = arg2

if shift = "" then failargs
if filename = "" then failargs
if file_exists(filename) = 0 then failargs

lines = file(filename)
linecount = count(lines)						            ' get the length of the array
linecounter = 0                                             ' init the counter for the loop

beginloop:

plaintext = strtolower(get_element(lines, linecounter))     ' output the current line

' initialization
length = strlen(plaintext)

counter = 0
output = ""


beginShiftLoop:
currentletter = substr(plaintext, counter, 1)               ' get the current letter as a substring of the plaintext
if is_alnum(currentletter) = 0 then skipLetter
currentletter = ord(currentletter) - 97                     ' subtract 97 (Decimal ASCII Value of "a") so we get numbers from 0-25
currentletter = currentletter + shift                       ' apply the caesar shift
currentletter = currentletter % 26                          ' do mod 26 to ensure the result is in the interval 0-25 and wraps around correctly
currentletter = currentletter + 97                          ' add the 97 as to get the right Decimal ASCII Value for the new letter
currentletter = chr(currentletter)                          ' convert the decimal value back to the ASCII Char

skipLetter:
output = output + currentletter                             ' collect the shifted letters

counter = counter + 1

if counter < length then beginShiftLoop                     ' loop until we worked on every letter of the plaintext

println output

linecounter = linecounter + 1                               ' increment the loop counter
if linecounter < linecount then beginloop                       ' break the loop when all lines are output

goto end

failargs:
println "[[;#f22;]Missing argument. Please specify a shift and a valid filename.]"

end: