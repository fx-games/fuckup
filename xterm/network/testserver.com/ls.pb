' list all the files on the current server, by memmaker

println ""                                      ' used to produce a blank line in the output, the "" is needed for the parser

if "bin" = arg1 then getbinaries                ' determine which directory to show

print "Listing of / on " + _ENV_CONNECTED_SERVER
allfiles = list_files()                         ' get list of files from the system

goto init

getbinaries:

print "Listing of /bin on " + _ENV_CONNECTED_SERVER
allfiles = list_files(1)                        ' get list of bin files from the system

init:

filecount = count(allfiles)                     ' get the length of the array

counter = 0                                     ' init the counter for the loop

println " - " + filecount + " files found"      ' print the file count, note the "" prefix is needed because pBasic infers the type from the left argument of a binary operator

println ""

beginloop:

println get_element(allfiles, counter)          ' output the current filename

counter = counter + 1                           ' increment the loop counter

if counter < filecount then beginloop           ' break the loop when all files are output

