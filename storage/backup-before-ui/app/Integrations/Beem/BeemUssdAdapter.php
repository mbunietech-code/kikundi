<?php

namespace App\Integrations\Beem;

use App\Services\Ussd\UssdReply;

/**
 * Maps Beem's USSD callback contract to the internal menu service.
 * See https://docs.beem.africa/api-reference/ussd/receive-ussd-session-callback
 *
 * Inbound:  {command, msisdn, operator, session_id, payload: {request_id, response}}
 * Outbound: {msisdn, operator, session_id, command, payload: {request_id, request}}
 */
class BeemUssdAdapter
{
    public function rules(): array
    {
        return [
            'command' => ['required', 'in:initiate,continue,terminate'],
            'msisdn' => ['required', 'string', 'max:20'],
            'operator' => ['nullable', 'string', 'max:30'],
            'session_id' => ['required', 'max:100'],
            'payload' => ['required', 'array'],
            'payload.request_id' => ['nullable'],
            'payload.response' => ['nullable'],
        ];
    }

    public function input(array $request): string
    {
        return (string) ($request['payload']['response'] ?? '');
    }

    public function format(array $request, UssdReply $reply, int $step): array
    {
        return [
            'msisdn' => (string) $request['msisdn'],
            'operator' => (string) ($request['operator'] ?? ''),
            'session_id' => (string) $request['session_id'],
            'command' => $reply->end ? 'terminate' : 'continue',
            'payload' => [
                'request_id' => $step,
                'request' => $reply->text,
            ],
        ];
    }
}
