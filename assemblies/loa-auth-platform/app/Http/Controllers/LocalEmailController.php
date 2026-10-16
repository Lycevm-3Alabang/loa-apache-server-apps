<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocalEmailSendRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class LocalEmailController extends Controller
{
    public function send(LocalEmailSendRequest $request)
    {
        $to = (array) $request->input('to', []);
        $cc = (array) $request->input('cc', []);
        $bcc = (array) $request->input('bcc', []);
        $subject = (string) $request->input('subject');
        $html = (string) $request->input('html');

        $allRecipients = array_values(array_merge($to, $cc, $bcc));
        $allowed = (array) config('mail-relay.allowed_domains', []);

        // Single-pass domain allowlist (ACC-2).
        $invalid = [];
        foreach ($allRecipients as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }
            $domain = trim(mb_strtolower((string) substr(strrchr($recipient, '@') ?: '', 1)));
            if (! in_array($domain, $allowed, true)) {
                $invalid[] = ['recipient' => $recipient, 'domain' => $domain];
            }
        }
        if ($invalid !== []) {
            Log::channel('mail-relay')->error('relay outcome=rejected_domain', [
                'recipients' => $allRecipients,
                'invalid' => $invalid,
            ]);
            return response()->json([
                'ok' => false,
                'error' => 'Recipient domain not allowed.',
                'invalid' => $invalid,
            ], 422);
        }

        $hosts = array_values(array_unique(array_map(
            fn ($recipient) => trim(mb_strtolower((string) substr(strrchr($recipient, '@') ?: '', 1))),
            $allRecipients
        )));

        try {
            $sent = Mail::html($html, function ($message) use ($to, $cc, $bcc, $subject): void {
                $message->to($to);
                if (count($cc) > 0) {
                    $message->cc($cc);
                }
                if (count($bcc) > 0) {
                    $message->bcc($bcc);
                }
                $message->from(
                    (string) config('mail.from.address', 'noreply@lyceumalabang.edu.ph'),
                    'ACES'
                );
                $message->subject($subject);
            });

            $messageId = $sent instanceof \Illuminate\Mail\SentMessage
                ? $sent->getMessageId()
                : null;

            Log::channel('mail-relay')->info('relay outcome=sent', [
                'to_domains' => $hosts,
                'subject' => mb_substr($subject, 0, 120),
                'outcome' => 'sent',
                'message_id' => $messageId,
            ]);

            return response()->json([
                'ok' => true,
                'message_id' => $messageId,
            ], Response::HTTP_OK);
        } catch (\Throwable $exception) {
            Log::channel('mail-relay')->error('relay outcome=failed', [
                'recipients' => $allRecipients,
                'subject' => mb_substr($subject, 0, 120),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Mail delivery failed.',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}
