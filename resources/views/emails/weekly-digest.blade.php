<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; color: #17181A; max-width: 560px; margin: 0 auto;">
    <p style="font-size: 14px; color: #6b7280;">{{ $d['week_label'] }}</p>
    <h1 style="font-size: 22px; margin: 0 0 16px;">Your training week</h1>

    @if (! $d['has_activity'])
        <p style="font-size: 15px; line-height: 1.5;">
            A quiet week &mdash; no sessions recorded. Rest is part of training,
            but if you meant to train, this is your nudge.
        </p>
    @else
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
            <tr>
                <td style="padding: 12px; background: #f4f4f5; border-radius: 8px; text-align: center;">
                    <div style="font-size: 26px; font-weight: 800;">{{ $d['load']['total'] }}</div>
                    <div style="font-size: 12px; color: #6b7280;">Load (TRIMP)</div>
                </td>
                <td style="width: 10px;"></td>
                <td style="padding: 12px; background: #f4f4f5; border-radius: 8px; text-align: center;">
                    <div style="font-size: 26px; font-weight: 800;">{{ $d['sessions'] }}</div>
                    <div style="font-size: 12px; color: #6b7280;">Sessions</div>
                </td>
                <td style="width: 10px;"></td>
                <td style="padding: 12px; background: #f4f4f5; border-radius: 8px; text-align: center;">
                    <div style="font-size: 26px; font-weight: 800;">{{ $d['adherence']['pct'] ?? '—' }}%</div>
                    <div style="font-size: 12px; color: #6b7280;">Adherence</div>
                </td>
            </tr>
        </table>

        <p style="font-size: 15px; line-height: 1.5;">
            Fitness (CTL) is <strong>{{ $d['form']['ctl'] ?? '—' }}</strong> and {{ $d['form']['trend'] }};
            form (TSB) sits at <strong>{{ $d['form']['tsb'] ?? '—' }}</strong>.
            Run {{ $d['load']['run'] }} / bike {{ $d['load']['bike'] }} TRIMP.
        </p>

        @if (count($d['prs']) > 0)
            <h2 style="font-size: 16px; margin: 20px 0 8px;">New personal bests</h2>
            <ul style="font-size: 15px; line-height: 1.6; padding-left: 18px;">
                @foreach ($d['prs'] as $pr)
                    <li>{{ $pr['label'] }} &mdash; <strong>{{ $pr['time'] }}</strong></li>
                @endforeach
            </ul>
        @endif
    @endif

    <h2 style="font-size: 16px; margin: 20px 0 8px;">Week ahead</h2>
    @if ($d['next_week']['count'] > 0)
        <ul style="font-size: 15px; line-height: 1.6; padding-left: 18px;">
            @foreach ($d['next_week']['sessions'] as $session)
                <li>{{ $session['date'] }}: {{ $session['title'] }}</li>
            @endforeach
        </ul>
    @else
        <p style="font-size: 15px; color: #6b7280;">Nothing planned yet. Time to map out the week.</p>
    @endif
</body>
</html>
