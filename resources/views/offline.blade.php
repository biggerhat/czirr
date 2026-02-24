<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>You're Offline - Czirr Family</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            html {
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
                background-color: #ffffff;
                color: #1a1a1a;
            }
            @media (prefers-color-scheme: dark) {
                html { background-color: #0a0a0a; color: #e5e5e5; }
            }
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 1.5rem;
            }
            .container {
                text-align: center;
                max-width: 28rem;
            }
            .icon {
                font-size: 3rem;
                margin-bottom: 1.5rem;
                opacity: 0.6;
            }
            h1 {
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 0.75rem;
            }
            p {
                color: #6b7280;
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            @media (prefers-color-scheme: dark) {
                p { color: #9ca3af; }
            }
            button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.625rem 1.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                border-radius: 0.5rem;
                border: 1px solid #d1d5db;
                background: #ffffff;
                color: #1a1a1a;
                cursor: pointer;
                transition: background-color 0.15s;
            }
            button:hover { background-color: #f3f4f6; }
            @media (prefers-color-scheme: dark) {
                button {
                    background: #1a1a1a;
                    color: #e5e5e5;
                    border-color: #374151;
                }
                button:hover { background-color: #262626; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">&#x1F4F6;</div>
            <h1>You're offline</h1>
            <p>It looks like you've lost your internet connection. Please check your connection and try again.</p>
            <button onclick="window.location.reload()">Try again</button>
        </div>
    </body>
</html>
