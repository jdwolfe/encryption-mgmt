#!/bin/bash

/usr/bin/s3cmd -c /home/jwolfe/.s3cfg --no-progress put /sonnet/encryption/skms/skms3.db s3://Eclipse-Storage
