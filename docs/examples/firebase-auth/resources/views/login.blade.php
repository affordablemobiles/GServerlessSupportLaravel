<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Nunito', sans-serif;
                font-weight: 200;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height">
            <div class="content">
                <div class="title m-b-md">
                    Laravel
                </div>

                <div style="margin-bottom: 30px;" id="firebaseui-auth-container"></div>
                <div style="margin-bottom: 30px;" id="loader">Loading...</div>
            </div>
        </div>

        <script src="/js/app.js"></script>

        <script src="https://www.gstatic.com/firebasejs/8.6.1/firebase.js"></script>
        <script>
            var config = {
                apiKey: "<< FIREBASE-API-KEY >>",
                authDomain: "<< FIREBASE-AUTH-DOMAIN >>",
            };
            firebase.initializeApp(config);
        </script>

        <script src="https://www.gstatic.com/firebasejs/ui/4.8.0/firebase-ui-auth.js"></script>
        <link type="text/css" rel="stylesheet" href="https://www.gstatic.com/firebasejs/ui/4.8.0/firebase-ui-auth.css" />

        <script>
            // As httpOnly cookies are to be used, do not persist any state client side.
            firebase.auth().setPersistence(firebase.auth.Auth.Persistence.NONE);

            // Initialize the FirebaseUI Widget using Firebase.
            var ui = new firebaseui.auth.AuthUI(firebase.auth());

            // Disable auto-sign in.
            ui.disableAutoSignIn();

            var uiConfig = {
                callbacks: {
                    signInSuccessWithAuthResult: function(authResult, redirectUrl) {
                        // User successfully signed in.
                        // Return type determines whether we continue the redirect automatically
                        // or whether we leave that to developer to handle.
                        return false;
                    },
                    uiShown: function() {
                        // The widget is rendered.
                        // Hide the loader.
                        document.getElementById('loader').style.display = 'none';
                    }
                },
                // Will use popup for IDP Providers sign-in flow instead of the default, redirect.
                signInFlow: 'redirect',
                signInSuccessUrl: '/',
                signInOptions: [
                    // Leave the lines as is for the providers you want to offer your users.
                    'apple.com',
                    {
                        // Google provider must be enabled in Firebase Console to support one-tap
                        // sign-up.
                        provider: firebase.auth.GoogleAuthProvider.PROVIDER_ID,
                        // Required to enable ID token credentials for this provider.
                        // This can be obtained from the Credentials page of the Google APIs
                        // console. Use the same OAuth client ID used for the Google provider
                        // configured with GCIP or Firebase Auth.
                        clientId: '<< GOOGLE-AUTH-CLIENT-ID >>'
                    },
                    firebase.auth.FacebookAuthProvider.PROVIDER_ID,
                    firebase.auth.TwitterAuthProvider.PROVIDER_ID,
                    'microsoft.com',
                    firebase.auth.EmailAuthProvider.PROVIDER_ID
                ],
                // Required to enable one-tap sign-up credential helper.
                credentialHelper: firebaseui.auth.CredentialHelper.GOOGLE_YOLO,
                // Terms of service url.
                tosUrl: '<< TOS-URL >>',
                // Privacy policy url.
                privacyPolicyUrl: '<< PRIVACY-POLICY-URL >>'
            };

            /**
            * Displays the UI for a signed in user.
            * @param {!firebase.User} user
            */
            var handleSignedInUser = function(user) {
                // Get the user's ID token as it is needed to exchange for a session cookie.
                return user.getIdToken().then(idToken => {
                    return axios.post('/login', {
                        idToken: idToken
                    })
                    .then((response) => {
                        if (response.status != 200) {
                            console.log('invalid response');
                            throw new Error('invalid response code');
                        }
                    }, (error) => {
                        console.log(error);
                        throw new Error('invalid response code');
                    });
                }).then(() => {
                    window.location.replace('/');
                });
            };

            /**
            * Displays the UI for a signed out user.
            */
            var handleSignedOutUser = function() {
                ui.start('#firebaseui-auth-container', uiConfig);
            };

            // Listen to change in auth state so it displays the correct UI for when
            // the user is signed in or not.
            firebase.auth().onAuthStateChanged(function(user) {
                user ? handleSignedInUser(user) : handleSignedOutUser();
            });
        </script>
    </body>
</html>
