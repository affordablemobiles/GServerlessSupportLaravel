#!/bin/bash

gcloud_email ()
{
    wget -qO- --header 'Metadata-Flavor:Google' "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/email" | cat
}

gcloud_token ()
{
    wget -qO- --header 'Metadata-Flavor:Google' "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token" | python3 -c 'import json,sys;print(json.load(sys.stdin)["access_token"])' | cat
}

echo -n "username="
echo $(gcloud_email)
echo -n "password="
echo $(gcloud_token)