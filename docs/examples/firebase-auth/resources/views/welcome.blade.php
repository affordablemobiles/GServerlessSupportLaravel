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
            <div class="top-right links">
                <a href="/logout">Logout</a>
            </div>

            <div class="content">
                <div class="title">
                    Laravel
                </div>

                @if(!empty(Auth::user()->picture))
                    <div class="links" style="margin-bottom: 20px;">
                        <img src="{{ Auth::user()->picture }}" style="border-radius: 50%; height: 70px; width: 70px; vertical-align: middle; border: 5px solid;" />
                    </div>
                @endif

                <div class="links">
                    <a href="#">{{ Auth::user()->name }}</a><br />
                    <a href="#">{{ Auth::user()->email }}</a><br />
                    <a href="#">Email Verified: {{ Auth::user()->email_verified ? 'Yes' : 'No' }}</a><br />
                    <a href="#">Signed in via: {{ Auth::user()->firebase['sign_in_provider'] }}</a><br />
                    <a href="#">Immutable User ID: {{ Auth::user()->sub }}</a>
                </div>
            </div>
        </div>
    </body>
</html>
