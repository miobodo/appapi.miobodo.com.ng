<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Document</title>

    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body {
        font-family: Arial, sans-serif;
      }

      header {
        width: 100%;
        border-top-width: 5px;
        border-top-color: #1b434e;
        border-top-style: solid;
        text-align: center;
        padding: 20px;
      }

      .span {
        padding: 0 10px;
        margin-top: -14px;
      }

      .span span {
        width: 100%;
        height: 1px;
        display: inline-block;
        background: #e3e3e3;
      }

      main {
        /* margin-top: 15px; */
        padding: 10px;
      }

      .footer {
        background-color: #f7f7f7;
        text-align: center;
        padding: 20px;
        font-size: 12px;
        margin-top: 50px;
        color: #777;
      }

      .footer a {
        color: #62d385;
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
    <header>
      <p style="font-size: 24px; font-weight: 500; color: #1b434e">{{ env("APP_NAME") }}</p>
      <p style="font-size: 13px; font-weight: 400; color: #1b434e95">
        Swift & ease.
      </p>
    </header>

    <div class="span">
      <span></span>
    </div>

    <main>
      <!-- title -->
      <p style="font-size: 15px; margin-top: 15px;">HELLO, {{ $username }}</p>
      <p
        style="
          margin-top: 7px;
          font-size: 14px;
          line-height: 20px;
          color: rgb(113, 113, 113);
        "
      >
        {{ $emailMessage }}
      </p>

      <!-- footer -->
      <div class="footer">
        <p>© {{ date('Y') }} {{ env('APP_NAME') }}. All rights reserved.</p>
        <p><a href="{{ env('BASE_URL') }}">Visit our website</a></p>
      </div>
    </main>
  </body>
</html>
