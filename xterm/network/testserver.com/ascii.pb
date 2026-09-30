' show a table of the printable ASCII chars, by memmaker

counter = 32

println "Dec, Hex, Bin, Char"

beginloop:

print counter

print ", " + dechex(counter) + "h"

print ", " + decbin(counter)

println ", " + chr(counter)

counter = counter + 1

if counter < 128 then beginloop