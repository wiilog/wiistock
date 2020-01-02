#!/bin/bash

function file::replaceInFile {
    match = $1;
    replace = $2;
    file = $3;
    sed -i "s/$match.*/$replace/" $file;
}

function remote::run() {
  # usage: remote::run "host" "includes" "commands"
  # where "includes" is a list of functions to export to
  # the remote host

  [[ -n "$2" ]] && includes="$(declare -f $2);"
  ssh -o StrictHostKeyChecking=no "$includes $3"
}
