#!/bin/bash


function replaceInFile() {
    match=$1
    replace=$2
    file=$3
    sed -i "s/$match.*/$replace/" "$file"
}

function containsElement() {
  local e match="$1"
  shift
  for e; do [[ "$e" == "$match" ]] && return 0; done
  return 1
}


function remote::run() {
    # usage: remote::run "host" "includes" "commands"
    # where "includes" is a list of functions to export to
    # the remote host

    [[ -n "$2" ]] && includes="$(declare -f $2);"
    ssh -o StrictHostKeyChecking=no "$includes $3"
}

function remote::changeEnv() {
    # usage: remote::changeEnv "$instance" "maintenance"
    instance=$1
    environment=$2
    changeEnv="cd /var/www/$instance/WiiStock && replaceInFile \"APP_ENV\" \"APP_ENV=$environment\" \".env\""
    remote::run serverName replaceInFile "$changeEnv"
}

function script::readInstance() {
    local instance
    local availableInstances=(dev test cl2-prod cl1-rec scs1-prod scs1-rec col1-prod col1-rec)
    while true; do
        read instance;
        instanceValid=$(containsElement "$instance" "${availableInstances[@]}")
        if [[ $instanceValid = 1 ]]; then
            echo 'instances disponibles : cl2-prod, cl1-rec, scs1-prod, scs1-rec, col1-prod, col1-rec, test, dev'
        else
            break
        fi
    done

    echo "$instance";
}

function script::getServerName() {
    instance=$1
    case "$instance" in
        dev | test) serverName='server-dev' ;;
        cl2-prod | cl1-rec | scs1-prod | scs1-rec) serverName='server-prod1' ;;
        col1-prod | col1-rec) serverName='server-prod2' ;;
        *) echo 'instances disponibles : cl2-prod, cl1-rec, scs1-prod, scs1-rec, col1-prod, col1-rec, test, dev' ;;
    esac

    echo $serverName;
}

function script::deploy() {
    # usage: remote::run "$serverName" "instance" "${commandsToRun[@]}"
    # commandsToRun should an array of string with format "commandToRun § successMessage § errorMessage"
    local serverName=$1
    shift
    local instance=$1
    shift
    local commandsToRun=("$@")
    local res
    cdProject="cd /var/www/$instance/WiiStock"
    for commandStr in "${commandsToRun[@]}"
    do
        IFS='§' read -ra command <<< "$commandStr"
        commandToRun="$cdProject && ${command[0]}"
        echo -e ">>>>>>>> RUN = $commandToRun"
        if [[ "$serverName" == "server-dev" ]]; then
            eval "$commandToRun"
        else
            remote::run "$serverName" replaceInFile "$commandToRun"
        fi

        res=$?

        local errorMessage="${command[2]}"
        if [[ "$errorMessage" != "" && "$res" != 0 ]]; then
            echo -e "$errorMessage"
            exit "$res";
        elif [[ "$res" == 0 ]]; then
            echo -e "${command[1]}"
        fi

    done
}
