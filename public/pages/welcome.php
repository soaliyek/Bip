<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Beep</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #D4CC9A 0%, #E2DFB0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .welcome-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            margin-bottom: 30px;
        }

        .logo-container img {
            width: 180px;
            height: auto;
        }

        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .tagline {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            font-weight: 300;
        }

        .disclaimer-box {
            background: #F6F6F6;
            border-left: 4px solid #D4CC9A;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
            border-radius: 8px;
        }

        .disclaimer-box h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .disclaimer-box p {
            color: #555;
            line-height: 1.8;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .disclaimer-box ul {
            margin: 15px 0;
            padding-left: 25px;
        }

        .disclaimer-box li {
            color: #555;
            line-height: 1.8;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .important-note {
            background: #FFF3CD;
            border: 1px solid #FFC107;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .important-note strong {
            color: #856404;
            font-size: 16px;
        }

        .important-note p {
            color: #856404;
            margin-top: 8px;
        }

        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #D4CC9A;
            color: #333;
            box-shadow: 0 4px 15px rgba(212, 204, 154, 0.3);
        }

        .btn-primary:hover {
            background: #C4BC8A;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 204, 154, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #666;
            border: 2px solid #ddd;
        }

        .btn-secondary:hover {
            background: #f9f9f9;
            border-color: #ccc;
        }

        @media (max-width: 600px) {
            .welcome-container {
                padding: 40px 25px;
            }

            h1 {
                font-size: 26px;
            }

            .tagline {
                font-size: 16px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="logo-container">
            <img src="../images/beep_logo.svg" alt="Beep Logo">
        </div>

        <h1>Welcome to Beep! 👋</h1>
        <p class="tagline">A safe space for peer support and connection</p>

        <div class="disclaimer-box">
            <h2>Before You Begin...</h2>
            
            <p>Beep is a <strong>peer-to-peer support platform</strong> designed to connect students who want to talk with trained peer counselors who want to listen.</p>

            <p><strong>Please understand:</strong></p>
            <ul>
                <li>This is <strong>NOT professional therapy</strong> or crisis intervention</li>
                <li>Peer counselors are students like you, not licensed professionals</li>
                <li>For serious mental health issues, please seek professional help</li>
                <li>In case of emergency, contact local emergency services</li>
            </ul>

            <p><strong>We expect all users to:</strong></p>
            <ul>
                <li>Be kind, respectful, and empathetic</li>
                <li>Keep conversations confidential</li>
                <li>Report inappropriate behavior immediately</li>
                <li>Use the platform responsibly and ethically</li>
            </ul>

            <div class="important-note">
                <strong>⚠️ Important</strong>
                <p>You use this platform at your own risk. While we work hard to create a safe environment, we cannot guarantee the behavior of all users.</p>
            </div>
        </div>

        <div class="action-buttons">
            <form method="POST" action="../../api/disclaimer.php" style="display: inline-block;">
                <input type="hidden" name="accept_disclaimer" value="1">
                <button type="submit" class="btn btn-primary">I Understand - Let's Go!</button>
            </form>
            <a href="../../authentication/logout.php" class="btn btn-secondary">Not Ready Yet</a>
        </div>
    </div>
</body>
</html>
