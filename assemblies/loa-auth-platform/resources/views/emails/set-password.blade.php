<!DOCTYPE html>
<html>
<head>
    <title>Set your LOA Platform password</title>
</head>
<body>
    <h1>Set your password</h1>

    <p>You have been added to the LOA Platform. Click the link below to set your password and activate your account.</p>

    <p><a href="{{ config('app.url') }}/set-password?token={{ $token }}">Set Password</a></p>

    <p>This link expires in 48 hours and can only be used once.</p>

    <p>If you did not expect this email, you can safely ignore it.</p>
</body>
</html>
