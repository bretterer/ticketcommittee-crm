@component('admin::emails.layout')
    <div>
        <p>Hi {{ $user->name }},</p>

        <p>You have <strong>{{ $unreadEmails->count() }}</strong> unread email(s) in your inbox.</p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
            <thead>
                <tr style="background-color: #f3f4f6; text-align: left;">
                    <th style="padding: 8px 12px; border: 1px solid #e5e7eb;">From</th>
                    <th style="padding: 8px 12px; border: 1px solid #e5e7eb;">Subject</th>
                    <th style="padding: 8px 12px; border: 1px solid #e5e7eb;">Received</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($unreadEmails as $email)
                    <tr>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">
                            {{ $email->name ?? (is_array($email->from) ? ($email->from[0] ?? 'Unknown') : 'Unknown') }}
                        </td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">
                            {{ $email->subject ?? '(no subject)' }}
                        </td>
                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; white-space: nowrap;">
                            {{ $email->created_at->format('M d, Y g:i A') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-top: 16px;">
            <a href="{{ route('admin.mail.index', ['route' => 'inbox']) }}" style="display: inline-block; padding: 8px 16px; background-color: #5c5cff; color: #ffffff; text-decoration: none; border-radius: 4px;">
                View Inbox
            </a>
        </p>
    </div>
@endcomponent
