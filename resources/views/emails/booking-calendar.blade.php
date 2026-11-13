<!doctype html>
<html>
<body style="margin:0;background:#0c0c18;color:#f8f9ff;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0c0c18;">
<tr><td align="center" style="padding:32px 14px;">
<table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;background:#161628;border:1px solid #2c3348;border-radius:18px;">
<tr><td style="padding:28px 32px 22px;text-align:center;border-bottom:3px solid #5ad8ff;">
<img src="{{ $message->embed(public_path('assets/brand/logo.png')) }}" width="180" alt="MusiciansWorld Creative Network" style="display:block;margin:0 auto 8px;">
<p style="margin:14px 0 0;color:#5ad8ff;font-size:12px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;">Add to your calendar</p>
</td></tr>
<tr><td style="padding:32px;">
<h1 style="margin:0 0 16px;color:#f8f9ff;font-size:30px;line-height:1.15;">Hi {{ $booking->customer_name }},</h1>
<p style="margin:0 0 14px;color:#b8c0d4;font-size:16px;line-height:1.7;">Thank you for booking {{ $booking->resource?->name ?: 'Studio A' }} with MusiciansWorld. The time, place, and details are below.</p>
<p style="margin:0 0 14px;color:#b8c0d4;font-size:16px;line-height:1.7;">Add this visit to your calendar with one of the buttons, or open the calendar file attached to this email.</p>
<table width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0 8px;border:1px solid #2c3348;border-radius:12px;">
@foreach($rows as $row)
<tr>
<td style="width:132px;padding:13px 16px;border-bottom:1px solid #2c3348;color:#8ea0c0;font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;vertical-align:top;">{{ $row['label'] }}</td>
<td style="padding:13px 16px;border-bottom:1px solid #2c3348;color:#f8f9ff;font-size:15px;font-weight:600;line-height:1.5;vertical-align:top;">{{ $row['value'] }}</td>
</tr>
@endforeach
</table>
<p style="margin:26px 0 12px;color:#8ea0c0;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;">Add to</p>
<p style="margin:0 0 12px;"><a href="{{ $links['google'] }}" style="background:#5ad8ff;color:#041018;text-decoration:none;padding:14px 20px;display:inline-block;font-weight:bold;border-radius:10px;">Google Calendar</a></p>
<p style="margin:0 0 12px;"><a href="{{ $links['download'] }}" style="background:#5ad8ff;color:#041018;text-decoration:none;padding:14px 20px;display:inline-block;font-weight:bold;border-radius:10px;">Outlook Calendar (ICS)</a></p>
<p style="margin:0 0 12px;"><a href="{{ $links['yahoo'] }}" style="background:#5ad8ff;color:#041018;text-decoration:none;padding:14px 20px;display:inline-block;font-weight:bold;border-radius:10px;">Yahoo Calendar</a></p>
<p style="margin:18px 0 0;"><a href="{{ $links['outlook'] }}" style="color:#5ad8ff;">Open in Microsoft Calendar</a></p>
<p style="margin:18px 0 0;color:#b8c0d4;font-size:14px;line-height:1.7;">The same calendar file is attached. If a button does not open, download that file and open it on your phone or computer.</p>
</td></tr>
<tr><td style="padding:22px 32px 28px;border-top:1px solid #2c3348;color:#8ea0c0;font-size:12px;line-height:1.7;">
MusiciansWorld Creative Network<br>
{{ LOCATION }} · {{ PHONE_PENDING }}<br>
{{ config('mail.from.address') }}<br>
CREATE. CONNECT. LEARN. PERFORM. GROW.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
