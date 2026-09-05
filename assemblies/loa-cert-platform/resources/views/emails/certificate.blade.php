<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Certificate</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; max-width: 600px; margin: 0 auto; padding: 24px;">
    <div style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 32px; border-radius: 12px 12px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px; font-weight: 700;">Certificate Issued</h1>
    </div>
    
    <div style="background: #f8fafc; padding: 32px; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="font-size: 16px; margin: 0 0 16px;">Hi <strong>{{ $recipientName }}</strong>,</p>
        
        <p style="font-size: 16px; margin: 0 0 24px;">Your certificate has been issued successfully. Here are the details:</p>
        
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; margin: 24px 0;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 30%;">Certificate Number</td>
                    <td style="padding: 8px 0; font-weight: 600; font-family: monospace;">{{ $certificateNumber }}</td>
                </tr>
                @if($eventName)
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Event</td>
                    <td style="padding: 8px 0; font-weight: 500;">{{ $eventName }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">Issued On</td>
                    <td style="padding: 8px 0; font-weight: 500;">{{ $issuedDate }}</td>
                </tr>
            </table>
        </div>
        
        <div style="text-align: center; margin: 32px 0;">
            @if($downloadUrl)
            <a href="{{ $downloadUrl }}" style="display: inline-block; background: #1e3a8a; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">
                Download Certificate
            </a>
            @endif
        </div>
        
        @if($verifyUrl)
        <p style="font-size: 14px; color: #6b7280; text-align: center; margin: 24px 0 0;">
            Verify this certificate: <a href="{{ $verifyUrl }}" style="color: #1e3a8a;">{{ $verifyUrl }}</a>
        </p>
        @endif
        
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 32px 0;">
        
        <p style="font-size: 12px; color: #9ca3af; margin: 0; text-align: center;">
            This is an automated message from LOA Certificate Platform. Please do not reply to this email.
        </p>
    </div>
</body>
</html>