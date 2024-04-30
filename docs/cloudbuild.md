## Cloud Build Deployments from Git

To simplify the deployments and make them re-producible, we can automate the process using Cloud Build from a Git repository in any of Cloud Source Repositories, BitBucket or GitHub.

This allows us to remove the reliance on developers having local copies of the repository, which previously had to be set up with all the relevant files that usually aren't booked in (.env for example), and for us became the main point of failure in the stack.

Cloud Build allows the deployments to be re-producible from clearly defined configuration that is no longer obscure, with the added bonus that it usually runs a lot quicker when it's all in the cloud already!

### 1. Cloud Build Configuration (inside the repository)
First, you'll need to add Cloud Build configuration to your repository.

As a starting point, you'll find an example `cloudbuild` folder, along with a useful `.gcloudignore` file in `docs/examples/laravel`. If you want to deploy within Cloud Run, instead of GAE, you'll find a "buildpack-cloud-run" folder for each in the same location.

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

## Using Secret Manager with Cloud Build

The newer, more accepted method of managing secrets with Cloud Build is using Secret Manager.

In the future, this will be further replaced by direct integration with Secret Manager from App Engine, similar to what Cloud Run has, but until then, we convert the secret references to plain text at deploy time.

Secrets can be managed in Secret Manager from Cloud Console, then referenced in the Cloud Build YAML config, as detailed in the examples.