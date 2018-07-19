#!/usr/bin/env bash

# trace ERR through pipes
set -o pipefail

# trace ERR through 'time command' and other functions
set -o errtrace

# set -u : exit the script if you try to use an uninitialised variable
set -o nounset

# set -e : exit the script if any statement returns a non-true return value
set -o errexit

sudo apt-get install -y beanstalkd

TRAVIS_CONFIG=/etc/default/beanstalkd

if [ ! -f "${TRAVIS_CONFIG}" ]; then
  echo -e "The ${TRAVIS_CONFIG} file does not exists."
  exit 1;
fi

echo -e "Beanstalk has been successfully installed."