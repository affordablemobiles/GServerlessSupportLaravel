## Cloud Build Deployments from Git

To simplify the deployments and make them re-producible, we can automate the process using Cloud Build from a Git repository in any of Cloud Source Repositories, BitBucket or GitHub.

This allows us to remove the reliance on developers having local copies of the repository, which previously had to be set up with all the relevant files that usually aren't booked in (.env for example), and for us became the main point of failure in the stack.

Cloud Build allows the deployments to be re-producible from clearly defined configuration that is no longer obscure, with the added bonus that it usually runs a lot quicker when it's all in the cloud already!

### 1. Cloud Build Configuration (inside the repository)
First, you'll need to add Cloud Build configuration to your repository.

As a starting point, you'll find an example `cloudbuild` folder, along with a useful `.gcloudignore` file in `docs/examples/laravel` for Laravel, or `docs/examples/lumen` for Lumen. If you want to deploy within Cloud Run, instead of GAE, you'll find a "buildpack-cloud-run" folder for each in the same location.

In both these examples, there is a single set of configuration and scripts to support a deployment called `LIVE`, but you can add extra sets of config/scripts to support multiple, such as `staging` and `test` as required.

The files you'll want to edit are:

* `cloudbuild/generate_env_LIVE.sh`
* `cloudbuild/assets/LIVE_app.yaml`

Also make sure you book in these files:

* `.gcloudignore`
* `composer.json`
* `composer.lock`
* `package.json`
* `package-lock.json`

### 2. Deployment Branch in Git
To make it obvious what is ready to deploy and also to protect the path to production with pull request permissions, we always have a deployment branch in the repository that has to be merged into via a pull request (BitBucket or GitHub).

We call this `deployment/live` for `LIVE`, but you can also have `deployment/test` for `test`, etc.

### 3. Cloud Build setup in Cloud Console

Add some trigers to your project in Cloud Console, specifying the branch as your deployment branch, e.g. `deployment/live`, plus the Cloud Build configuration as the path to the yaml file, e.g. `cloudbuild/cloudbuild_live.yaml`.


https://console.cloud.google.com/cloud-build/triggers

## Encrypting Secrets with KMS and Cloud Build

While it is considered bad practice to store application secrets in the repository along with the application (and for very good reason), this is at odds with what we are trying to achieve with a clearly defined set of configuration to be deployed directly from the repository via Cloud Build.

One perfect way around this is to store encrypted secrets in the repository, so we can still have everything all in one place, without exposing anything outside of production and the person setting up the build.

### 1. Create the KMS keyring

Run the command (replacing "\<project-id\>" with your project ID):

```
gcloud kms keyrings create deployment --location=global --project=<project-id>
```

### 2. Create the KMS key

Run the command (replacing "\<project-id\>" with your project ID):

```
gcloud kms keys create cloudbuild --purpose=encryption --location=global --keyring=deployment --project=<project-id>
```

### 3. Grant Cloud Build permission to decrypt

Run the command (replacing "\<project-id\>" with your project ID and "\<cloudbuild_sa\>" with the Cloud Build service account email/ID):

```
gcloud kms keys add-iam-policy-binding cloudbuild --keyring=deployment --location=global --member=serviceAccount:<cloudbuild_sa> --role=roles/cloudkms.cryptoKeyDecrypter --project=<project-id>
```

### 4. Encrypt a secret with KMS

```
echo -n "<secret string>" |\
 gcloud kms encrypt --project="<project-id>" --location=global --keyring=deployment --key=cloudbuild --ciphertext-file=- --plaintext-file=- |\
 base64
```

### 5. Add the secret to the Cloud Build yaml

```yaml
steps:
- name: "gcr.io/cloud-builders/gcloud"
  entrypoint: "bash"
  args: ["./cloudbuild/generate_env_LIVE.sh"]
  secretEnv: ['<SECRET_NAME>']
...
secrets:
- kmsKeyName: projects/<project-id>/locations/global/keyRings/deployment/cryptoKeys/cloudbuild
  secretEnv:
    <SECRET_NAME>: <ENCRYPTED_BASE64_DATA>
```

### 6. Use the ENV variable in `generate_env_LIVE.sh`

```
...
<ENV_NAME>=$<SECRET_NAME>
...
```

### 7. Commit changes to the repository & deploy...

### 8. Decrypt a secret (for debugging)

```
echo -n "<base64'd encrypted secret from Cloud Build file>" |\
 base64 -d |\
 gcloud kms decrypt --project="<source-project-id>" --location=global --keyring=deployment --key=cloudbuild --ciphertext-file=- --plaintext-file=-
```

### 9. Transfer a secret to another project without viewing it

```
echo -n "<base64'd encrypted secret from Cloud Build file>" |\
 base64 -d |\
 gcloud kms decrypt --project="<source-project-id>" --location=global --keyring=deployment --key=cloudbuild --ciphertext-file=- --plaintext-file=- |\
 gcloud kms encrypt --project="<destination-project-id>" --location=global --keyring=deployment --key=cloudbuild --ciphertext-file=- --plaintext-file=- |\
 base64
```
