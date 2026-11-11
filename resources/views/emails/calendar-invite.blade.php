<!doctype html>
<html>
<body style="margin:0;background:#07070f;color:#f7f3ef;font-family:Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#07070f">
<tr><td align="center" style="padding:30px 14px">
<table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#11111b;border:1px solid #292938">
<tr><td style="padding:36px">
<p style="color:#f05a28;text-transform:uppercase;letter-spacing:2px;font-size:12px">MusiciansWorld</p>
<h1 style="font-size:28px;margin:10px 0 18px">{{ $headline }}</h1>
<p style="color:#c8c5c2;line-height:1.7">{{ $name }}, the calendar file is attached. Open it to add this time to the calendar on your phone or computer, or use one of the buttons below.</p>
<p style="margin:28px 0 12px"><a href="{{ $links['google'] }}" style="background:#f05a28;color:#fff;text-decoration:none;padding:14px 20px;display:inline-block;font-weight:bold">Add to Google Calendar</a></p>
<p style="margin:0 0 12px"><a href="{{ $links['outlook'] }}" style="background:#f05a28;color:#fff;text-decoration:none;padding:14px 20px;display:inline-block;font-weight:bold">Add to Microsoft Calendar</a></p>
<p style="margin:0"><a href="{{ $links['download'] }}" style="color:#f05a28">Download the calendar file</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
