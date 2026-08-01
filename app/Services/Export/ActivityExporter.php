<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Activity;
use App\Models\User;
use App\Support\Payload;
use Illuminate\Support\Facades\Storage;

class ActivityExporter
{
    /**
     * Per-activity record stream as CSV, or a header-only file when no stream
     * is archived.
     */
    public function streamCsv(Activity $activity): string
    {
        $streams = $this->streams($activity);
        $lines = ['timestamp,hr,speed_mps,lat,lng'];

        $t = $streams['t'] ?? [];
        foreach ($t as $i => $ts) {
            $lines[] = implode(',', [
                Payload::toInt($ts),
                $this->cell($streams['hr'][$i] ?? null),
                $this->cell($streams['speed'][$i] ?? null),
                $this->cell($streams['lat'][$i] ?? null),
                $this->cell($streams['lng'][$i] ?? null),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Per-activity TCX. Emits a single lap with the archived trackpoints.
     */
    public function tcx(Activity $activity): string
    {
        $streams = $this->streams($activity);
        $t = $streams['t'] ?? [];

        $trackpoints = '';
        foreach ($t as $i => $ts) {
            $time = gmdate('Y-m-d\TH:i:s\Z', Payload::toInt($ts));
            $trackpoints .= "        <Trackpoint>\n          <Time>{$time}</Time>\n";
            $lat = $streams['lat'][$i] ?? null;
            $lng = $streams['lng'][$i] ?? null;
            if (is_numeric($lat) && is_numeric($lng)) {
                $trackpoints .= "          <Position><LatitudeDegrees>{$lat}</LatitudeDegrees><LongitudeDegrees>{$lng}</LongitudeDegrees></Position>\n";
            }
            $hr = $streams['hr'][$i] ?? null;
            if ($hr !== null) {
                $trackpoints .= '          <HeartRateBpm><Value>'.Payload::toInt($hr)."</Value></HeartRateBpm>\n";
            }
            $trackpoints .= "        </Trackpoint>\n";
        }

        $startTime = gmdate('Y-m-d\TH:i:s\Z', $activity->started_at->getTimestamp());
        $sport = $activity->sport->value === 'bike' ? 'Biking' : 'Running';
        $distance = (float) ($activity->distance_m ?? 0);
        $seconds = $activity->duration_s ?? 0;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TrainingCenterDatabase xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2">
  <Activities>
    <Activity Sport="{$sport}">
      <Id>{$startTime}</Id>
      <Lap StartTime="{$startTime}">
        <TotalTimeSeconds>{$seconds}</TotalTimeSeconds>
        <DistanceMeters>{$distance}</DistanceMeters>
        <Track>
{$trackpoints}        </Track>
      </Lap>
    </Activity>
  </Activities>
</TrainingCenterDatabase>

XML;
    }

    /**
     * Every activity for the athlete with its derived metrics, as one CSV.
     */
    public function allActivitiesCsv(User $user): string
    {
        $header = 'date,sport,distance_m,duration_s,avg_hr,trimp,decoupling,efficiency_factor,cardiac_cost,hr_drift';
        $lines = [$header];

        $user->activities()
            ->orderBy('started_at')
            ->get(['started_at', 'sport', 'distance_m', 'duration_s', 'avg_hr', 'trimp', 'decoupling', 'efficiency_factor', 'cardiac_cost', 'hr_drift'])
            ->each(function (Activity $activity) use (&$lines): void {
                $lines[] = implode(',', [
                    $activity->started_at->toDateString(),
                    $activity->sport->value,
                    $this->cell($activity->distance_m),
                    $this->cell($activity->duration_s),
                    $this->cell($activity->avg_hr),
                    $this->cell($activity->trimp),
                    $this->cell($activity->decoupling),
                    $this->cell($activity->efficiency_factor),
                    $this->cell($activity->cardiac_cost),
                    $this->cell($activity->hr_drift),
                ]);
            });

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function streams(Activity $activity): array
    {
        if ($activity->streams_path === null || ! Storage::disk('local')->exists($activity->streams_path)) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get($activity->streams_path), true);

        return Payload::streams($decoded);
    }

    private function cell(mixed $value): string
    {
        return $value === null ? '' : Payload::toStr($value);
    }
}
