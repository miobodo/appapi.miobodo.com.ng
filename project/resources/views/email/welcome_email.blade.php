<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to right, #8E2DE2, #4A00E0);
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background-color: #fff;
            padding: 10px;
            border-radius: 0px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(to right, #4A00E0, #8E2DE2);
            color: white;
            text-align: center;
            padding: 40px 0;
        }

        .header h1 {
            font-size: 24px;
            margin: 0;
        }

        .header p {
            font-size: 16px;
            margin-top: 10px;
            color: #f0f0f0;
        }

        .content {
            padding: 30px;
            text-align: center;
        }

        .content p {
            font-size: 16px;
            line-height: 1.6;
            margin: 10px 0;
        }

        .button-container {
            margin-top: 20px;
        }

        .btn {
            background-color: #4A00E0;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 6px;
            display: inline-block;
            font-size: 16px;
        }

        .btn:hover {
            background-color: #8E2DE2;
        }

        .footer {
            background-color: #f7f7f7;
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #777;
        }

        .footer a {
            color: #4A00E0;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .footer p {
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{ env('APP_NAME') }}!</h1>
            <p>We're glad you're here</p>
        </div>

        <div class="content">
            <p>Hello!</p>
            <p>We’re thrilled to have you with us. Thank you for joining {{ env('APP_NAME') }}. We're committed to delivering the best experience possible.</p>
            
            <div class="button-container">
                <a href="{{ env('BASE_URL') }}" class="btn">Get Started</a>
            </div>
            
            <p>If you have any questions, feel free to <a href="{{ env('BASE_URL') }}/contact">contact us</a>.</p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} {{ env('APP_NAME') }}. All rights reserved.</p>
            <p><a href="{{ env('BASE_URL') }}">Visit our website</a></p>
        </div>
    </div>
</body>
</html>
