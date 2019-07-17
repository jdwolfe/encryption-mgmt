#!/bin/bash

/usr/bin/s3cmd -c /home/jwolfe/.s3cfg --no-progress put /sonnet/encryption/kms/kms1.db s3://Misc-Storage
